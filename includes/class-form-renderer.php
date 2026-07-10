<?php
/**
 * Renders form CPT posts by ID or slug.
 *
 * @package PRC\Platform\Block_Forms
 */

namespace PRC\Platform\Block_Forms;

/**
 * Shared frontend renderer for form CPT content.
 *
 * Uses parse_blocks() + WP_Block::render() (not do_blocks()) so
 * prc-block/form/formPostId context propagates to nested form blocks.
 *
 * @package PRC\Platform\Block_Forms
 */
class Form_Renderer {
	/**
	 * Tracks form IDs currently being rendered to prevent infinite recursion.
	 *
	 * @var array<int, bool>
	 */
	private static array $seen_refs = array();

	/**
	 * Look up a form CPT post by slug (post_name).
	 *
	 * @param string $slug Form post slug.
	 * @return \WP_Post|null
	 */
	public static function get_form_by_slug( string $slug ): ?\WP_Post {
		if ( '' === $slug || ! post_type_exists( Forms::POST_TYPE ) ) {
			return null;
		}

		$form = get_page_by_path( $slug, OBJECT, Forms::POST_TYPE );

		if ( ! $form instanceof \WP_Post || Forms::POST_TYPE !== $form->post_type ) {
			return null;
		}

		return $form;
	}

	/**
	 * Render a form CPT post by slug.
	 *
	 * @param string               $slug    Form post slug.
	 * @param array<string, mixed> $context Optional block context merged with formPostId.
	 * @return string Rendered HTML.
	 */
	public static function render_by_slug( string $slug, array $context = array() ): string {
		$form = self::get_form_by_slug( $slug );

		if ( ! $form ) {
			return '';
		}

		return self::render_by_id( (int) $form->ID, $context );
	}

	/**
	 * Render a form CPT post by ID.
	 *
	 * @param int                  $form_id Form post ID.
	 * @param array<string, mixed> $context Optional block context merged with formPostId.
	 * @return string Rendered HTML.
	 */
	public static function render_by_id( int $form_id, array $context = array() ): string {
		if ( $form_id <= 0 ) {
			return '';
		}

		$form_post = get_post( $form_id );
		if ( ! $form_post || Forms::POST_TYPE !== $form_post->post_type ) {
			return '';
		}

		if ( isset( self::$seen_refs[ $form_id ] ) ) {
			$is_debug = WP_DEBUG && WP_DEBUG_DISPLAY;

			return $is_debug ?
				// translators: Visible only in the front end, this warning takes the place of a faulty block.
				__( '[block rendering halted]' ) :
				'';
		}

		$allowed_statuses = array( 'publish' );
		if ( is_user_logged_in() || is_preview() ) {
			$allowed_statuses[] = 'draft';
			$allowed_statuses[] = 'future';
			$allowed_statuses[] = 'private';
		} elseif ( ! empty( $form_post->post_password ) ) {
			return '';
		}

		if ( ! in_array( $form_post->post_status, $allowed_statuses, true ) ) {
			return '';
		}

		self::$seen_refs[ $form_id ] = true;

		$parsed  = parse_blocks( $form_post->post_content );
		$content = '';
		$context = array_merge(
			array( 'prc-block/form/formPostId' => $form_id ),
			$context
		);

		foreach ( $parsed as $parsed_block ) {
			$content .= ( new \WP_Block( $parsed_block, $context ) )->render();
		}

		unset( self::$seen_refs[ $form_id ] );

		return $content;
	}
}
