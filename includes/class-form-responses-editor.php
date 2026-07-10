<?php
/**
 * Form Responses panel in the Form CPT block editor.
 *
 * @package PRC\Platform\Block_Forms
 */

namespace PRC\Platform\Block_Forms;

/**
 * Enqueues the document-sidebar panel that opens a modal DataViews app
 * scoped to the current form.
 *
 * @package PRC\Platform\Block_Forms
 */
class Form_Responses_Editor {
	/**
	 * Constructor.
	 *
	 * @param \PRC\Platform\Block_Forms\Loader $loader The loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'enqueue_block_editor_assets', $this, 'enqueue_editor_assets' );
	}

	/**
	 * Enqueue the responses panel on the Form CPT editor screen (admins only).
	 *
	 * @hook enqueue_block_editor_assets
	 */
	public function enqueue_editor_assets() {
		if ( ! current_user_can( Forms::get_responses_capability() ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Forms::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$build_dir  = PRC_BLOCK_FORMS_DIR . '/build/form-responses-panel/';
		$asset_file = $build_dir . 'index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset  = include $asset_file;
		$handle = 'prc-form-responses-panel';

		wp_enqueue_script(
			$handle,
			plugins_url( 'build/form-responses-panel/index.js', PRC_BLOCK_FORMS_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( file_exists( $build_dir . 'style-index.css' ) ) {
			wp_enqueue_style(
				$handle,
				plugins_url( 'build/form-responses-panel/style-index.css', PRC_BLOCK_FORMS_FILE ),
				array( 'wp-components' ),
				$asset['version']
			);
		}

		wp_localize_script(
			$handle,
			'prcFormResponses',
			Form_Responses_Assets::get_localized_data()
		);
	}
}
