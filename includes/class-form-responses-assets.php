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
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'restUrl'         => esc_url_raw( rest_url() ),
			'forms'           => self::get_forms_list(),
			'statuses'        => $filter_options['statuses'],
			'legacyFormNames' => $filter_options['legacy_form_names'],
		);
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
