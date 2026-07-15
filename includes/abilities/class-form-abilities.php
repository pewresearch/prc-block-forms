<?php
/**
 * Form CPT abilities (Jetpack Forms–shaped).
 *
 * @package PRC\Platform\Block_Forms
 */

declare(strict_types=1);

namespace PRC\Platform\Block_Forms;

/**
 * Registers list/get/create/delete form abilities.
 */
class Form_Abilities {

	/**
	 * Plugin file used to validate activation on the target site.
	 *
	 * @var string
	 */
	private const PLUGIN_FILE = 'prc-block-forms/prc-block-forms.php';

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Plugin loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'wp_abilities_api_init', $this, 'register_abilities' );
	}

	/**
	 * Register form abilities.
	 *
	 * @hook wp_abilities_api_init
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'prc-block-forms/list-forms',
			array(
				'label'               => __( 'List forms (admin)', 'prc-block-forms' ),
				'description'         => __( 'List PRC block forms (form CPT) with response counts, status, and edit URLs. Supports pagination, search, and status filtering.', 'prc-block-forms' ),
				'category'            => Form_Ability_Categories::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'additionalProperties' => false,
					'properties'           => array(
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Page number for paginated results.',
							'default'     => 1,
							'minimum'     => 1,
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Number of forms per page.',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 100,
						),
						'search'   => array(
							'type'        => 'string',
							'description' => 'Search forms by title.',
						),
						'status'   => array(
							'type'        => 'string',
							'description' => 'Filter by form status.',
							'enum'        => array( 'publish', 'draft', 'trash' ),
						),
						'site_id'  => \PRC\Platform\AI\Utils\site_id_input_schema_property(),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => function ( $input = array() ) {
					return $this->with_site(
						$input,
						function () use ( $input ) {
							return $this->list_forms( is_array( $input ) ? $input : array() );
						}
					);
				},
				'permission_callback' => function ( $input = null ) {
					return $this->with_site(
						$input,
						function () use ( $input ) {
							return $this->can_edit_forms( $input );
						}
					);
				},
				'meta'                => $this->readonly_meta( 'List reusable form CPT posts used by Synced Form embeds.' ),
			)
		);

		wp_register_ability(
			'prc-block-forms/get-form',
			array(
				'label'               => __( 'Get form details', 'prc-block-forms' ),
				'description'         => __( 'Get a single PRC block form with its field definitions, status, and edit URL. Sensitive actionConfig values (e.g. forwardTo) are redacted.', 'prc-block-forms' ),
				'category'            => Form_Ability_Categories::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'additionalProperties' => false,
					'properties'           => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'The form ID.',
						),
						'site_id' => \PRC\Platform\AI\Utils\site_id_input_schema_property(),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => function ( $input = array() ) {
					return $this->with_site(
						$input,
						function () use ( $input ) {
							return $this->get_form( is_array( $input ) ? $input : array() );
						}
					);
				},
				'permission_callback' => function ( $input = array() ) {
					return $this->with_site(
						$input,
						function () use ( $input ) {
							return $this->can_get_form( is_array( $input ) ? $input : array() );
						}
					);
				},
				'meta'                => $this->readonly_meta( 'Returns form CPT metadata plus parsed prc-block/form-input-* fields. Block content has sensitive actionConfig values redacted.' ),
			)
		);

		wp_register_ability(
			'prc-block-forms/create-form',
			array(
				'label'               => __( 'Create a form', 'prc-block-forms' ),
				'description'         => __( 'Create a new PRC block form with a title. Optionally provide Gutenberg block content. Prefer status=draft unless the user explicitly asked to publish. Returns the new form ID and edit URL.', 'prc-block-forms' ),
				'category'            => Form_Ability_Categories::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'title' ),
					'additionalProperties' => false,
					'properties'           => array(
						'title'   => array(
							'type'        => 'string',
							'description' => 'The form title/name.',
						),
						'content' => array(
							'type'        => 'string',
							'description' => 'Block content for the form structure. If omitted, creates an empty form with a submit button.',
						),
						'status'  => array(
							'type'        => 'string',
							'description' => 'Initial form status.',
							'enum'        => array( 'publish', 'draft' ),
							'default'     => 'publish',
						),
						'site_id' => \PRC\Platform\AI\Utils\site_id_input_schema_property(),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => function ( $input = array() ) {
					return $this->with_site(
						$input,
						function () use ( $input ) {
							return $this->create_form( is_array( $input ) ? $input : array() );
						}
					);
				},
				'permission_callback' => function ( $input = null ) {
					return $this->with_site(
						$input,
						function () use ( $input ) {
							return $this->can_edit_forms( $input );
						}
					);
				},
				'meta'                => $this->write_meta( false, false ),
			)
		);

		wp_register_ability(
			'prc-block-forms/delete-form',
			array(
				'label'               => __( 'Delete a form', 'prc-block-forms' ),
				'description'         => __( 'Move a PRC block form to the trash. Does not permanently delete. Trashed forms can be restored.', 'prc-block-forms' ),
				'category'            => Form_Ability_Categories::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'additionalProperties' => false,
					'properties'           => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'The form ID to delete.',
						),
						'site_id' => \PRC\Platform\AI\Utils\site_id_input_schema_property(),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => function ( $input = array() ) {
					return $this->with_site(
						$input,
						function () use ( $input ) {
							return $this->delete_form( is_array( $input ) ? $input : array() );
						}
					);
				},
				'permission_callback' => function ( $input = array() ) {
					return $this->with_site(
						$input,
						function () use ( $input ) {
							return $this->can_delete_form( is_array( $input ) ? $input : array() );
						}
					);
				},
				'meta'                => $this->write_meta( true, true ),
			)
		);
	}

	/**
	 * @return bool
	 */
	public function can_edit_forms( $input = null ): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_get_form( $input = array() ): bool {
		$id = absint( $input['id'] ?? 0 );
		if ( ! $id ) {
			return current_user_can( 'edit_posts' );
		}
		return current_user_can( 'edit_post', $id );
	}

	/**
	 * @param array $input Ability input.
	 * @return bool
	 */
	public function can_delete_form( $input = array() ): bool {
		$id = absint( $input['id'] ?? 0 );
		if ( ! $id ) {
			return current_user_can( 'delete_posts' );
		}
		return current_user_can( 'delete_post', $id );
	}

	/**
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function list_forms( $input = array() ) {
		$page     = max( 1, absint( $input['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, absint( $input['per_page'] ?? 10 ) ) );
		$search   = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
		$status   = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'any';

		$query_args = array(
			'post_type'      => Forms::POST_TYPE,
			'post_status'    => in_array( $status, array( 'publish', 'draft', 'trash' ), true ) ? $status : array( 'publish', 'draft', 'private' ),
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}
		// Limit to the current author's forms when the user cannot edit others' posts.
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$query_args['author'] = get_current_user_id();
		}

		$query = new \WP_Query( $query_args );
		$ids   = wp_list_pluck( $query->posts, 'ID' );
		$counts = ( new Form_Response_Repository() )->count_by_form_ids( $ids );

		$forms = array();
		foreach ( $query->posts as $post ) {
			$forms[] = array(
				'id'             => (int) $post->ID,
				'title'          => get_the_title( $post ),
				'status'         => $post->post_status,
				'slug'           => $post->post_name,
				'date'           => $post->post_date_gmt,
				'modified'       => $post->post_modified_gmt,
				'edit_url'       => get_edit_post_link( $post->ID, 'raw' ),
				'response_count' => $counts[ (int) $post->ID ] ?? 0,
			);
		}

		return array(
			'forms'       => $forms,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		);
	}

	/**
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function get_form( $input = array() ) {
		$id   = absint( $input['id'] ?? 0 );
		$post = get_post( $id );
		if ( ! $post || Forms::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'not_found', 'Form not found.', array( 'status' => 404 ) );
		}

		$structure = Form_Structure::from_content( (string) $post->post_content );
		$counts    = ( new Form_Response_Repository() )->get_folder_counts( $id );

		return array(
			'id'             => (int) $post->ID,
			'title'          => get_the_title( $post ),
			'status'         => $post->post_status,
			'slug'           => $post->post_name,
			'content'        => Form_Structure::redact_content( (string) $post->post_content ),
			'edit_url'       => get_edit_post_link( $post->ID, 'raw' ),
			'form'           => $structure['form'],
			'fields'         => $structure['fields'],
			'response_count' => $counts['inbox'] + $counts['spam'],
			'inbox_count'    => $counts['inbox'],
			'spam_count'     => $counts['spam'],
			'unread_count'   => $counts['unread'],
		);
	}

	/**
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function create_form( $input = array() ) {
		$title   = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : '';
		$content = isset( $input['content'] ) ? (string) $input['content'] : '';
		$status  = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'publish';

		if ( '' === $title ) {
			return new \WP_Error( 'invalid_title', 'Title is required.', array( 'status' => 400 ) );
		}
		if ( ! in_array( $status, array( 'publish', 'draft' ), true ) ) {
			$status = 'publish';
		}
		if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
			$status = 'draft';
		}
		if ( '' === trim( $content ) ) {
			$content = Form_Structure::empty_form_content( $title );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => Forms::POST_TYPE,
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => $status,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return array(
			'id'       => (int) $post_id,
			'title'    => $title,
			'status'   => get_post_status( $post_id ),
			'edit_url' => get_edit_post_link( $post_id, 'raw' ),
		);
	}

	/**
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function delete_form( $input = array() ) {
		$id   = absint( $input['id'] ?? 0 );
		$post = get_post( $id );
		if ( ! $post || Forms::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'not_found', 'Form not found.', array( 'status' => 404 ) );
		}

		$result = wp_trash_post( $id );
		if ( ! $result ) {
			return new \WP_Error( 'delete_failed', 'Failed to trash form.', array( 'status' => 500 ) );
		}

		return array(
			'id'     => $id,
			'status' => 'trash',
		);
	}

	/**
	 * @param string $instructions Agent hint.
	 * @return array
	 */
	private function readonly_meta( string $instructions ): array {
		return array(
			'annotations'  => array(
				'instructions' => $this->with_site_instructions( $instructions ),
				'readonly'     => true,
				'destructive'  => false,
				'idempotent'   => true,
			),
			'show_in_rest' => true,
			'mcp'          => array(
				'public' => true,
				'type'   => 'tool',
			),
		);
	}

	/**
	 * @param bool $destructive Destructive annotation.
	 * @param bool $idempotent  Idempotent annotation.
	 * @return array
	 */
	private function write_meta( bool $destructive, bool $idempotent ): array {
		return array(
			'annotations'  => array(
				'instructions' => $this->with_site_instructions( 'Runs this form write operation on the target site.' ),
				'readonly'     => false,
				'destructive'  => $destructive,
				'idempotent'   => $idempotent,
			),
			'show_in_rest' => true,
			'mcp'          => array(
				'public' => true,
				'type'   => 'tool',
			),
		);
	}

	/**
	 * Run a callback on the requested target site.
	 *
	 * @param array|null $input    Ability input.
	 * @param callable   $callback Callback to run after site validation/switching.
	 * @return mixed
	 */
	private function with_site( $input, callable $callback ) {
		return \PRC\Platform\AI\Utils\with_site(
			\PRC\Platform\AI\Utils\resolve_site_id( is_array( $input ) ? $input : null ),
			self::PLUGIN_FILE,
			$callback
		);
	}

	/**
	 * Append standard site targeting instructions.
	 *
	 * @param string $instructions Base instructions.
	 * @return string
	 */
	private function with_site_instructions( string $instructions ): string {
		return trim( $instructions ) . ' Optionally pass site_id to run against a specific multisite blog; defaults to the content site (20). If this plugin is inactive on the target site, the ability returns plugin_inactive_on_site.';
	}
}
