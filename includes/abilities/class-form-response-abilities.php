<?php
/**
 * Form response abilities (Jetpack Forms–shaped).
 *
 * @package PRC\Platform\Block_Forms
 */

declare(strict_types=1);

namespace PRC\Platform\Block_Forms;

/**
 * Registers get-responses / update / bulk-update / status-counts abilities.
 */
class Form_Response_Abilities {

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Plugin loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'wp_abilities_api_init', $this, 'register_abilities' );
	}

	/**
	 * Register response abilities.
	 *
	 * @hook wp_abilities_api_init
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'prc-block-forms/get-responses',
			array(
				'label'               => __( 'Get form responses', 'prc-block-forms' ),
				'description'         => __( 'List or search PRC block form responses. Returns sender info, fields, spam/unread state, and metadata. Supports filtering by form, source post, spam folder, unread, search, and date bounds.', 'prc-block-forms' ),
				'category'            => 'data-retrieval',
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'additionalProperties' => false,
					'properties'           => array(
						'ids'            => array(
							'type'        => 'array',
							'description' => 'Fetch specific responses by their IDs.',
							'items'       => array( 'type' => 'integer' ),
						),
						'page'           => array(
							'type'        => 'integer',
							'default'     => 1,
							'minimum'     => 1,
						),
						'per_page'       => array(
							'type'        => 'integer',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 100,
						),
						'form_id'        => array(
							'type'        => 'integer',
							'description' => 'Filter by form CPT ID.',
						),
						'parent'         => array(
							'type'        => 'array',
							'description' => 'Filter by source page/post ID(s) where the form was embedded (Jetpack parent analogue).',
							'items'       => array( 'type' => 'integer' ),
						),
						'status'         => array(
							'type'        => 'string',
							'description' => 'Jetpack-style folder: publish/inbox, spam, or trash (trash returns empty — PRC hard-deletes).',
							'enum'        => array( 'publish', 'draft', 'spam', 'trash', 'inbox' ),
						),
						'is_unread'      => array(
							'type'        => 'boolean',
							'description' => 'Set true for unread only, false for read only.',
						),
						'search'         => array(
							'type'        => 'string',
							'description' => 'Search within response content and sender info.',
						),
						'before'         => array(
							'type'        => 'string',
							'format'      => 'date-time',
							'description' => 'Only responses before this date (ISO8601).',
						),
						'after'          => array(
							'type'        => 'string',
							'format'      => 'date-time',
							'description' => 'Only responses after this date (ISO8601).',
						),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( $this, 'get_responses' ),
				'permission_callback' => array( $this, 'can_manage_responses' ),
				'meta'                => $this->meta( true, false, true ),
			)
		);

		wp_register_ability(
			'prc-block-forms/update-response',
			array(
				'label'               => __( 'Update form response', 'prc-block-forms' ),
				'description'         => __( 'Modify a form response. Use to mark as spam, restore to inbox, hard-delete (trash), or toggle read/unread state. PRC has no soft-trash folder — trash permanently deletes.', 'prc-block-forms' ),
				'category'            => 'data-modification',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'additionalProperties' => false,
					'properties'           => array(
						'id'        => array(
							'type'        => 'integer',
							'description' => 'The response ID to update.',
						),
						'status'    => array(
							'type'        => 'string',
							'description' => 'New status: publish/inbox (restore), spam, trash (permanent delete).',
							'enum'        => array( 'publish', 'draft', 'spam', 'trash', 'inbox' ),
						),
						'is_unread' => array(
							'type'        => 'boolean',
							'description' => 'Set false to mark as read, true to mark as unread.',
						),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( $this, 'update_response' ),
				'permission_callback' => array( $this, 'can_manage_responses' ),
				'meta'                => $this->meta( false, true, false ),
			)
		);

		wp_register_ability(
			'prc-block-forms/bulk-update-responses',
			array(
				'label'               => __( 'Bulk update form responses', 'prc-block-forms' ),
				'description'         => __( 'Mark multiple responses as spam/not spam or read/unread. Each response is processed individually; the result reports per-id success and failures.', 'prc-block-forms' ),
				'category'            => 'data-modification',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'action', 'ids' ),
					'additionalProperties' => false,
					'properties'           => array(
						'action' => array(
							'type'        => 'string',
							'description' => 'The bulk action to perform.',
							'enum'        => array( 'mark_as_spam', 'mark_as_not_spam', 'mark_as_read', 'mark_as_unread' ),
						),
						'ids'    => array(
							'type'        => 'array',
							'description' => 'Response IDs to update.',
							'items'       => array( 'type' => 'integer' ),
							'minItems'    => 1,
						),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( $this, 'bulk_update_responses' ),
				'permission_callback' => array( $this, 'can_manage_responses' ),
				'meta'                => $this->meta( false, false, true ),
			)
		);

		wp_register_ability(
			'prc-block-forms/get-status-counts',
			array(
				'label'               => __( 'Get response status counts', 'prc-block-forms' ),
				'description'         => __( 'Get a summary of form responses grouped by folder. Returns counts for inbox, spam, and unread (non-spam unread). PRC has no trash folder.', 'prc-block-forms' ),
				'category'            => 'data-retrieval',
				'input_schema'        => array(
					'type'                 => 'object',
					'default'              => array(),
					'additionalProperties' => false,
					'properties'           => array(
						'search'    => array(
							'type'        => 'string',
							'description' => 'Only count responses matching this search term.',
						),
						'form_id'   => array(
							'type'        => 'integer',
							'description' => 'Only count responses for a specific form CPT ID.',
						),
						'parent'    => array(
							'type'        => 'integer',
							'description' => 'Only count responses from a specific source page/post.',
						),
						'before'    => array(
							'type'   => 'string',
							'format' => 'date-time',
						),
						'after'     => array(
							'type'   => 'string',
							'format' => 'date-time',
						),
						'is_unread' => array(
							'type'        => 'boolean',
							'description' => 'When set, recompute folder counts using only matching unread/read rows.',
						),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( $this, 'get_status_counts' ),
				'permission_callback' => array( $this, 'can_manage_responses' ),
				'meta'                => $this->meta( true, false, true ),
			)
		);
	}

	/**
	 * @return bool
	 */
	public function can_manage_responses(): bool {
		return current_user_can( Forms::get_responses_capability() );
	}

	/**
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function get_responses( $input = array() ) {
		$repository = new Form_Response_Repository();

		if ( ! empty( $input['ids'] ) && is_array( $input['ids'] ) ) {
			$items = array();
			foreach ( $input['ids'] as $id ) {
				$row = $repository->get( absint( $id ) );
				if ( $row ) {
					$items[] = $this->prepare_response( $row );
				}
			}
			return array(
				'responses'   => $items,
				'total'       => count( $items ),
				'total_pages' => 1,
				'page'        => 1,
				'per_page'    => count( $items ),
			);
		}

		$status = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'inbox';
		if ( 'trash' === $status ) {
			return array(
				'responses'   => array(),
				'total'       => 0,
				'total_pages' => 1,
				'page'        => 1,
				'per_page'    => absint( $input['per_page'] ?? 10 ),
				'note'        => 'PRC form responses have no trash folder; deleted responses are permanently removed.',
			);
		}

		$is_spam = in_array( $status, array( 'spam' ), true ) ? 1 : 0;
		$parent  = 0;
		if ( ! empty( $input['parent'] ) && is_array( $input['parent'] ) ) {
			$parent = absint( $input['parent'][0] ?? 0 );
		} elseif ( ! empty( $input['parent'] ) ) {
			$parent = absint( $input['parent'] );
		}

		$query_args = array(
			'page'           => absint( $input['page'] ?? 1 ),
			'per_page'       => absint( $input['per_page'] ?? 10 ),
			'form_id'        => absint( $input['form_id'] ?? 0 ),
			'source_post_id' => $parent,
			'search'         => isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '',
			'is_spam'        => $is_spam,
			'before'         => isset( $input['before'] ) ? (string) $input['before'] : '',
			'after'          => isset( $input['after'] ) ? (string) $input['after'] : '',
			'order'          => 'DESC',
		);

		if ( array_key_exists( 'is_unread', $input ) && null !== $input['is_unread'] ) {
			$query_args['is_unread'] = $input['is_unread'] ? 1 : 0;
		}

		$result = $repository->query( $query_args );

		return array(
			'responses'   => array_map( array( $this, 'prepare_response' ), $result['items'] ),
			'total'       => $result['total'],
			'total_pages' => $result['pages'],
			'page'        => max( 1, absint( $input['page'] ?? 1 ) ),
			'per_page'    => max( 1, min( 100, absint( $input['per_page'] ?? 10 ) ) ),
		);
	}

	/**
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function update_response( $input = array() ) {
		$repository = new Form_Response_Repository();
		$id         = absint( $input['id'] ?? 0 );
		$row        = $repository->get( $id );

		if ( ! $row ) {
			return new \WP_Error( 'not_found', 'Form response not found.', array( 'status' => 404 ) );
		}

		$status = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : '';
		if ( 'trash' === $status ) {
			$deleted = $repository->delete( array( $id ) );
			return array(
				'id'      => $id,
				'deleted' => (bool) $deleted,
				'status'  => 'trash',
			);
		}

		if ( in_array( $status, array( 'spam' ), true ) ) {
			$repository->set_spam( array( $id ), true );
		} elseif ( in_array( $status, array( 'publish', 'draft', 'inbox' ), true ) ) {
			$repository->set_spam( array( $id ), false );
		}

		if ( array_key_exists( 'is_unread', $input ) && null !== $input['is_unread'] ) {
			$repository->set_unread( array( $id ), (bool) $input['is_unread'] );
		}

		$updated = $repository->get( $id );
		return $this->prepare_response( $updated ? $updated : $row );
	}

	/**
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function bulk_update_responses( $input = array() ) {
		$action     = isset( $input['action'] ) ? sanitize_key( (string) $input['action'] ) : '';
		$ids        = isset( $input['ids'] ) && is_array( $input['ids'] ) ? array_map( 'absint', $input['ids'] ) : array();
		$repository = new Form_Response_Repository();

		$allowed = array( 'mark_as_spam', 'mark_as_not_spam', 'mark_as_read', 'mark_as_unread' );
		if ( ! in_array( $action, $allowed, true ) ) {
			return new \WP_Error( 'invalid_action', 'Unsupported bulk action.', array( 'status' => 400 ) );
		}
		if ( empty( $ids ) ) {
			return new \WP_Error( 'invalid_ids', 'At least one response ID is required.', array( 'status' => 400 ) );
		}

		$success = array();
		$failed  = array();

		foreach ( $ids as $id ) {
			$row = $id ? $repository->get( $id ) : null;
			if ( ! $row ) {
				$failed[] = array(
					'id'    => $id,
					'error' => 'not_found',
				);
				continue;
			}

			switch ( $action ) {
				case 'mark_as_spam':
					$repository->set_spam( array( $id ), true );
					$ok = ! empty( $repository->get( $id )['is_spam'] );
					break;
				case 'mark_as_not_spam':
					$repository->set_spam( array( $id ), false );
					$updated_row = $repository->get( $id );
					$ok          = $updated_row && empty( $updated_row['is_spam'] );
					break;
				case 'mark_as_read':
					$repository->set_unread( array( $id ), false );
					$updated_row = $repository->get( $id );
					$ok          = $updated_row && empty( $updated_row['is_unread'] );
					break;
				case 'mark_as_unread':
					$repository->set_unread( array( $id ), true );
					$ok = ! empty( $repository->get( $id )['is_unread'] );
					break;
				default:
					$ok = false;
					break;
			}

			if ( $ok ) {
				$success[] = $id;
			} else {
				$failed[] = array(
					'id'    => $id,
					'error' => 'update_failed',
				);
			}
		}

		return array(
			'action'  => $action,
			'success' => $success,
			'failed'  => $failed,
			'updated' => count( $success ),
		);
	}

	/**
	 * @param array $input Ability input.
	 * @return array
	 */
	public function get_status_counts( $input = array() ) {
		$form_id = absint( $input['form_id'] ?? 0 );
		$parent  = absint( $input['parent'] ?? 0 );

		// When only form_id is set (common path), use the optimized folder counts.
		if ( $form_id && ! $parent && empty( $input['search'] ) && empty( $input['before'] ) && empty( $input['after'] ) && ! array_key_exists( 'is_unread', $input ) ) {
			return ( new Form_Response_Repository() )->get_folder_counts( $form_id );
		}

		$repository = new Form_Response_Repository();
		$base       = array(
			'form_id'        => $form_id,
			'source_post_id' => $parent,
			'search'         => isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '',
			'before'         => isset( $input['before'] ) ? (string) $input['before'] : '',
			'after'          => isset( $input['after'] ) ? (string) $input['after'] : '',
			'per_page'       => 1,
			'page'           => 1,
		);

		if ( array_key_exists( 'is_unread', $input ) && null !== $input['is_unread'] ) {
			$base['is_unread'] = $input['is_unread'] ? 1 : 0;
		}

		$inbox = $repository->query( array_merge( $base, array( 'is_spam' => 0 ) ) );
		$spam  = $repository->query( array_merge( $base, array( 'is_spam' => 1 ) ) );

		if ( array_key_exists( 'is_unread', $input ) && false === $input['is_unread'] ) {
			$unread_total = 0;
		} else {
			$unread       = $repository->query( array_merge( $base, array( 'is_spam' => 0, 'is_unread' => 1 ) ) );
			$unread_total = $unread['total'];
		}

		return array(
			'inbox'  => $inbox['total'],
			'spam'   => $spam['total'],
			'unread' => $unread_total,
		);
	}

	/**
	 * Shape a repository row for ability output (snake_case).
	 *
	 * @param array $row Repository row.
	 * @return array
	 */
	private function prepare_response( array $row ): array {
		$form_title = '';
		if ( ! empty( $row['form_id'] ) ) {
			$form_post = get_post( $row['form_id'] );
			if ( $form_post && Forms::POST_TYPE === $form_post->post_type ) {
				$form_title = $form_post->post_title;
			}
		}

		return array(
			'id'             => $row['id'],
			'created_at'     => $row['created_at'],
			'form_id'        => $row['form_id'],
			'form_name'      => $row['form_name'],
			'form_title'     => $form_title,
			'action'         => $row['action'],
			'status'         => $row['status'],
			'email'          => $row['email'],
			'from_name'      => $row['from_name'],
			'fields'         => $row['fields'],
			'source_url'     => $row['source_url'],
			'source_post_id' => $row['source_post_id'],
			'user_id'        => $row['user_id'],
			'user_agent'     => $row['user_agent'],
			'is_spam'        => ! empty( $row['is_spam'] ),
			'is_unread'      => ! empty( $row['is_unread'] ),
		);
	}

	/**
	 * @param bool $readonly    Readonly annotation.
	 * @param bool $destructive Destructive annotation.
	 * @param bool $idempotent  Idempotent annotation.
	 * @return array
	 */
	private function meta( bool $readonly, bool $destructive, bool $idempotent ): array {
		return array(
			'annotations'  => array(
				'readonly'    => $readonly,
				'destructive' => $destructive,
				'idempotent'  => $idempotent,
			),
			'show_in_rest' => true,
			'mcp'          => array(
				'public' => true,
				'type'   => 'tool',
			),
		);
	}
}
