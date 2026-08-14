<?php

namespace PRC\Platform\Block_Forms;

class Forms_REST_Controller {
	/**
	 * Constructor.
	 *
	 * @param \PRC\Platform\Block_Forms\Loader $loader The loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'rest_api_init', $this, 'register_rest_endpoints' );
	}

	/**
	 * Register the form library route.
	 *
	 * @hook rest_api_init
	 */
	public function register_rest_endpoints() {
		register_rest_route(
			'prc-api/v3',
			'form/library',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_library' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'page'          => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page'      => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'search'        => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status'        => array(
						'type'              => 'string',
						'default'           => 'publish,draft,pending,private',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'orderby'       => array(
						'type'    => 'string',
						'default' => 'date',
						'enum'    => array( 'date', 'modified', 'title', 'author', 'fields' ),
					),
					'order'         => array(
						'type'    => 'string',
						'default' => 'desc',
						'enum'    => array( 'asc', 'desc', 'ASC', 'DESC' ),
					),
					'action'        => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'method'        => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'watchingOnly'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'activeEditors' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Permission callback for the form library.
	 *
	 * @return bool
	 */
	public function permission_callback() {
		return current_user_can( Form_List::get_capability() );
	}

	/**
	 * List forms for the DataViews admin screen.
	 *
	 * @param \WP_REST_Request $request The incoming request.
	 * @return \WP_REST_Response
	 */
	public function get_library( \WP_REST_Request $request ) {
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );

		$statuses = array_values(
			array_filter(
				array_map( 'sanitize_key', explode( ',', (string) $request->get_param( 'status' ) ) )
			)
		);
		if ( empty( $statuses ) ) {
			$statuses = array( 'publish', 'draft', 'pending', 'private' );
		}

		$query_args = array(
			'post_type'              => Forms::POST_TYPE,
			'post_status'            => $statuses,
			'perm'                   => 'editable',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			's'                      => (string) $request->get_param( 'search' ),
			'no_found_rows'          => false,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => true,
		);

		$order   = 'asc' === strtolower( (string) $request->get_param( 'order' ) ) ? 'ASC' : 'DESC';
		$orderby = (string) $request->get_param( 'orderby' );

		// Meta filters/sorts INNER JOIN on list meta and hide unsynced forms.
		// Run one bounded batch (same as admin_init) before those queries.
		if ( $this->library_query_needs_list_meta( $request, $orderby ) ) {
			Form_List::run_backfill_batch();
		}

		if ( 'fields' === $orderby ) {
			$query_args['orderby']  = 'meta_value_num';
			$query_args['meta_key'] = Form_List::META_FIELD_COUNT; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		} elseif ( in_array( $orderby, array( 'title', 'modified', 'author' ), true ) ) {
			$query_args['orderby'] = $orderby;
		} else {
			$query_args['orderby'] = 'date';
		}
		$query_args['order'] = $order;

		$meta_query = $this->build_library_meta_query( $request );
		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		// Shell providers (Working on / Active editors) map request args → WP_Query.
		$query_args = apply_filters(
			'prc_wp_admin_dataview_query_args',
			$query_args,
			$request,
			Forms::POST_TYPE
		);

		$query = new \WP_Query( $query_args );

		$ids = array_map(
			static function ( $post ) {
				return (int) $post->ID;
			},
			$query->posts
		);

		$can_view_responses = current_user_can( Forms::get_responses_capability() );
		$counts             = array();
		$unread_counts      = array();

		if ( $can_view_responses && ! empty( $ids ) ) {
			$repository    = new Form_Response_Repository();
			$counts        = $repository->count_by_form_ids( $ids );
			$unread_counts = $repository->unread_count_by_form_ids( $ids );
		}

		$rows = array_map(
			function ( $post ) use ( $counts, $unread_counts, $can_view_responses ) {
				return apply_filters(
					'prc_wp_admin_dataview_shape_row',
					$this->shape_library_row( $post, $counts, $unread_counts, $can_view_responses ),
					$post,
					Forms::POST_TYPE
				);
			},
			$query->posts
		);

		$response = rest_ensure_response( $rows );
		$response->header( 'X-WP-Total', (string) (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) (int) $query->max_num_pages );

		return $response;
	}

	private function library_query_needs_list_meta( \WP_REST_Request $request, string $orderby ) {
		return 'fields' === $orderby
			|| ! empty( $this->build_library_meta_query( $request ) );
	}

	private function build_library_meta_query( \WP_REST_Request $request ) {
		$meta_query = array();

		$actions = array_values(
			array_filter( array_map( 'sanitize_text_field', explode( ',', (string) $request->get_param( 'action' ) ) ) )
		);
		if ( ! empty( $actions ) ) {
			$meta_query[] = array(
				'key'     => Form_List::META_ACTION,
				'value'   => $actions,
				'compare' => 'IN',
			);
		}

		$methods = array_values(
			array_filter( array_map( 'sanitize_key', explode( ',', (string) $request->get_param( 'method' ) ) ) )
		);
		if ( ! empty( $methods ) ) {
			$meta_query[] = array(
				'key'     => Form_List::META_METHOD,
				'value'   => $methods,
				'compare' => 'IN',
			);
		}

		return $meta_query;
	}

	/**
	 * Shape a GET row. When list meta is missing, parse block markup and store
	 * it so later list queries can filter in SQL.
	 */
	private function shape_library_row( \WP_Post $post, array $counts, array $unread_counts, bool $can_view_responses ) {
		$has_meta = metadata_exists( 'post', $post->ID, Form_List::META_ACTION );

		if ( ! $has_meta ) {
			$parsed = Form_List::parse_list_meta( (string) $post->post_content );
			Form_List::store_list_meta( $post->ID, $parsed );
			$action      = $parsed['action'];
			$method      = $parsed['method'];
			$field_count = (int) $parsed['field_count'];
		} else {
			$action      = (string) get_post_meta( $post->ID, Form_List::META_ACTION, true );
			$method      = (string) get_post_meta( $post->ID, Form_List::META_METHOD, true );
			$field_count = (int) get_post_meta( $post->ID, Form_List::META_FIELD_COUNT, true );
		}

		$row = array(
			'id'             => (int) $post->ID,
			'title'          => get_the_title( $post ),
			'status'         => $post->post_status,
			'previousStatus' => (string) get_post_meta( $post->ID, '_wp_trash_meta_status', true ),
			'date'           => mysql2date( 'c', $post->post_date, false ),
			'modified'       => mysql2date( 'c', $post->post_modified, false ),
			'edit_url'       => (string) get_edit_post_link( $post->ID, 'raw' ),
			'author'         => (string) get_the_author_meta( 'display_name', $post->post_author ),
			'action'         => $action,
			'method'         => $method,
			'field_count'    => $field_count,
		);

		// Match the responses routes: only expose counts when the caller can
		// read form responses.
		if ( $can_view_responses ) {
			$row['responses'] = (int) ( $counts[ (int) $post->ID ] ?? 0 );
			$row['unread']    = (int) ( $unread_counts[ (int) $post->ID ] ?? 0 );
		}

		return $row;
	}
}
