<?php
/**
 * Form Responses admin page (DataViews listing of form responses).
 *
 * @package PRC\Platform\Block_Forms
 */

namespace PRC\Platform\Block_Forms;

/**
 * Registers the Forms → Responses admin page and enqueues the DataViews app.
 *
 * @package PRC\Platform\Block_Forms
 */
class Form_Responses_Admin {
	/**
	 * Menu slug for the responses screen.
	 */
	const ADMIN_PAGE_SLUG = 'prc-form-responses';

	/**
	 * Constructor.
	 *
	 * @param \PRC\Platform\Block_Forms\Loader $loader The loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'admin_menu', $this, 'register_admin_page' );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_assets' );
	}

	/**
	 * Register the Responses page under the Forms menu.
	 *
	 * @hook admin_menu
	 */
	public function register_admin_page() {
		add_submenu_page(
			'edit.php?post_type=' . Forms::POST_TYPE,
			__( 'Form Responses', 'prc-block-forms' ),
			__( 'Responses', 'prc-block-forms' ),
			Forms::get_responses_capability(),
			self::ADMIN_PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the admin page container for the React app.
	 */
	public function render_admin_page() {
		echo '<div id="prc-form-responses-admin"></div>';
	}

	/**
	 * Enqueue the React app only on the Responses page.
	 *
	 * @hook admin_enqueue_scripts
	 *
	 * @param string $hook_suffix The current admin page hook.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( Forms::POST_TYPE . '_page_' . self::ADMIN_PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$build_dir  = PRC_BLOCK_FORMS_DIR . '/build/response-admin/';
		$asset_file = $build_dir . 'index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset  = include $asset_file;
		$handle = 'prc-form-responses-admin';

		wp_enqueue_script(
			$handle,
			plugins_url( 'build/response-admin/index.js', PRC_BLOCK_FORMS_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( file_exists( $build_dir . 'style-index.css' ) ) {
			wp_enqueue_style(
				$handle,
				plugins_url( 'build/response-admin/style-index.css', PRC_BLOCK_FORMS_FILE ),
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
