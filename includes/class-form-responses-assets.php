<?php
/**
 * Shared script localization for form responses admin UIs.
 *
 * @package PRC\Platform\Block_Forms
 */

namespace PRC\Platform\Block_Forms;

/**
 * Provides localized data for the responses DataViews app.
 *
 * @package PRC\Platform\Block_Forms
 */
class Form_Responses_Assets {
	/**
	 * Data passed to the responses React app via wp_localize_script.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_localized_data() {
		$filter_options = ( new Form_Response_Repository() )->get_filter_options();

		return array(
			'nonce'               => wp_create_nonce( 'wp_rest' ),
			'restUrl'             => esc_url_raw( rest_url() ),
			'forms'               => self::get_forms_list(),
			'statuses'            => $filter_options['statuses'],
			'legacyFormNames'     => $filter_options['legacy_form_names'],
			'sources'             => self::get_sources_list( $filter_options['source_post_ids'], $filter_options['source_urls'] ),
			'canCreateCrmContact' => Form_List::is_crm_plugin_active(),
		);
	}

	/**
	 * Elements for the Source filter. Values are prefixed like the Form filter:
	 * `post:<id>` targets `source_post_id`, `url:<url>` targets `source_url`.
	 *
	 * @param int[]    $post_ids Source post IDs, most used first.
	 * @param string[] $urls     Source URLs with no post, most used first.
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function get_sources_list( array $post_ids, array $urls ) {
		$sources = array();

		if ( $post_ids ) {
			_prime_post_caches( $post_ids, false, false );
		}

		foreach ( $post_ids as $post_id ) {
			$title     = get_the_title( $post_id );
			$sources[] = array(
				'value' => 'post:' . $post_id,
				/* translators: %d: post ID */
				'label' => '' !== $title ? wp_strip_all_tags( $title ) : sprintf( __( 'Post #%d', 'prc-block-forms' ), $post_id ),
			);
		}

		foreach ( $urls as $url ) {
			$path      = wp_parse_url( $url, PHP_URL_PATH );
			$sources[] = array(
				'value' => 'url:' . $url,
				'label' => $path ? $path : $url,
			);
		}

		return $sources;
	}

	/**
	 * List of form posts for the Form filter dropdown.
	 *
	 * @return array<int, array{id: int, title: string}>
	 */
	public static function get_forms_list() {
		$forms = get_posts(
			array(
				'post_type'        => Forms::POST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'private', 'future' ),
				'posts_per_page'   => 100,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		return array_map(
			static function ( $form ) {
				return array(
					'id'    => $form->ID,
					'title' => $form->post_title ? $form->post_title : __( '(no title)', 'prc-block-forms' ),
				);
			},
			$forms
		);
	}
}
