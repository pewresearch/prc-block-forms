<?php
/**
 * REST controller for the form responses admin UI.
 *
 * @package PRC\Platform\Block_Forms
 */

namespace PRC\Platform\Block_Forms;

/**
 * A thin REST layer over Form_Response_Repository, powering the
 * Forms → Responses DataViews admin app.
 *
 * @package PRC\Platform\Block_Forms
 */
class Form_Responses_REST_Controller {
	/**
	 * Constructor.
	 *
	 * @param \PRC\Platform\Block_Forms\Loader $loader The loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'rest_api_init', $this, 'register_rest_endpoints' );
	}

	/**
	 * Permission callback shared by every responses route.
	 *
	 * @return bool
	 */
	public function permission_callback() {
		return current_user_can( Forms::get_responses_capability() );
	}

	/**
	 * Register the responses REST routes.
	 *
	 * @hook rest_api_init
	 */
	public function register_rest_endpoints() {
		register_rest_route(
			'prc-api/v3',
			'form/responses',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_responses' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'page'      => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 25,
					),
					'search'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'form_id'   => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'form_name' => array(
						'type'    => 'string',
						'default' => '',
					),
					'status'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'is_spam'   => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'order'     => array(
						'type'    => 'string',
						'default' => 'desc',
					),
				),
			)
		);

		register_rest_route(
			'prc-api/v3',
			'form/responses/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_response' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_response' ),
					'permission_callback' => array( $this, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			'prc-api/v3',
			'form/responses/bulk-delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'bulk_delete_responses' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'ids' => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array(
							'type' => 'integer',
						),
					),
				),
			)
		);

		register_rest_route(
			'prc-api/v3',
			'form/responses/spam',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_responses_spam' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'ids'     => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array(
							'type' => 'integer',
						),
					),
					'is_spam' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * List responses with pagination headers.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function get_responses( $request ) {
		$repository = new Form_Response_Repository();

		$result = $repository->query(
			array(
				'page'      => $request->get_param( 'page' ),
				'per_page'  => $request->get_param( 'per_page' ),
				'search'    => sanitize_text_field( $request->get_param( 'search' ) ),
				'form_id'   => absint( $request->get_param( 'form_id' ) ),
				'form_name' => sanitize_text_field( $request->get_param( 'form_name' ) ),
				'status'    => sanitize_text_field( $request->get_param( 'status' ) ),
				'is_spam'   => absint( $request->get_param( 'is_spam' ) ),
				'order'     => $request->get_param( 'order' ),
			)
		);

		$items  = array_map( array( $this, 'prepare_response_for_ui' ), $result['items'] );
		$counts = $repository->get_folder_counts(
			absint( $request->get_param( 'form_id' ) ),
			sanitize_text_field( $request->get_param( 'form_name' ) )
		);

		$response = new \WP_REST_Response( $items, 200 );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['pages'] );
		$response->header( 'X-PRC-Inbox-Total', (string) $counts['inbox'] );
		$response->header( 'X-PRC-Spam-Total', (string) $counts['spam'] );

		return $response;
	}

	/**
	 * Get a single response, including its full field payload.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_response( $request ) {
		$repository = new Form_Response_Repository();
		$row        = $repository->get( absint( $request->get_param( 'id' ) ) );

		if ( ! $row ) {
			return new \WP_Error( 'not_found', 'Form response not found.', array( 'status' => 404 ) );
		}

		return new \WP_REST_Response( $this->prepare_response_for_ui( $row ), 200 );
	}

	/**
	 * Delete a single response.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_response( $request ) {
		$repository = new Form_Response_Repository();
		$id         = absint( $request->get_param( 'id' ) );

		if ( ! $repository->get( $id ) ) {
			return new \WP_Error( 'not_found', 'Form response not found.', array( 'status' => 404 ) );
		}

		$deleted = $repository->delete( array( $id ) );

		if ( ! $deleted ) {
			return new \WP_Error( 'delete_failed', 'Failed to delete form response.', array( 'status' => 500 ) );
		}

		return new \WP_REST_Response(
			array(
				'deleted' => $deleted,
			),
			200
		);
	}

	/**
	 * Delete multiple responses.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function bulk_delete_responses( $request ) {
		$ids        = (array) $request->get_param( 'ids' );
		$repository = new Form_Response_Repository();

		return new \WP_REST_Response(
			array(
				'deleted' => $repository->delete( $ids ),
			),
			200
		);
	}

	/**
	 * Move responses into or out of the spam folder.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function set_responses_spam( $request ) {
		$ids        = (array) $request->get_param( 'ids' );
		$is_spam    = (bool) $request->get_param( 'is_spam' );
		$repository = new Form_Response_Repository();

		return new \WP_REST_Response(
			array(
				'updated' => $repository->set_spam( $ids, $is_spam ),
			),
			200
		);
	}

	/**
	 * Shape a repository row for the DataViews app: resolve the parent form
	 * title/edit link and the source post title.
	 *
	 * @param array $row Formatted repository row.
	 * @return array
	 */
	public function prepare_response_for_ui( array $row ) {
		$form_title    = '';
		$form_edit_url = '';
		if ( $row['form_id'] ) {
			$form_post = get_post( $row['form_id'] );
			if ( $form_post && Forms::POST_TYPE === $form_post->post_type ) {
				$form_title    = $form_post->post_title;
				$form_edit_url = get_edit_post_link( $form_post->ID, 'raw' );
			}
		}

		$source_title = '';
		if ( $row['source_post_id'] ) {
			$source_title = get_the_title( $row['source_post_id'] );
		}

		return array(
			'id'           => $row['id'],
			'date'         => $row['created_at'],
			'formId'       => $row['form_id'],
			'formName'     => $row['form_name'],
			'formTitle'    => $form_title,
			'formEditUrl'  => $form_edit_url,
			'action'       => $row['action'],
			'status'       => $row['status'],
			'email'        => $row['email'],
			'fromName'     => $row['from_name'],
			'fields'       => $row['fields'],
			'sourceUrl'    => $row['source_url'],
			'sourcePostId' => $row['source_post_id'],
			'sourceTitle'  => $source_title,
			'userId'       => $row['user_id'],
			'userAgent'    => $row['user_agent'],
			'isSpam'       => $row['is_spam'],
		);
	}
}
