<?php
/**
 * Plugin class.
 *
 * @package PRC\Platform\Block_Forms
 */

declare(strict_types=1);

namespace PRC\Platform\Block_Forms;

/**
 * Bootstraps the prc-block-forms plugin.
 */
class Plugin {

	protected Loader $loader;

	protected string $plugin_name = 'prc-block-forms';

	protected string $version = '1.0.0';

	public function __construct() {
		$this->load_dependencies();
		$this->register_block_metadata_collection();
		$this->init_dependencies();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies(): void {
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-loader.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-forms.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-form-renderer.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-form-structure.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-form-send-email.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-form-response-log.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-form-patterns.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-form-sample-provisioner.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/abilities/class-abilities.php';
		$this->load_form_blocks();

		$this->loader = new Loader();
	}

	/**
	 * Load block PHP classes from src (local) or build (deployed).
	 */
	private function load_form_blocks(): void {
		$dev_mode  = 'local' === wp_get_environment_type();
		$block_src = $dev_mode ? 'src' : 'build';
		$blocks    = array(
			'synced-form',
			'form',
			'form-captcha',
			'form-message',
			'form-page',
			'form-submit',
		);

		foreach ( $blocks as $block ) {
			$block_class = PRC_BLOCK_FORMS_DIR . "/{$block_src}/{$block}/class-{$block}.php";
			if ( file_exists( $block_class ) ) {
				require_once $block_class;
			}
		}
	}

	/**
	 * Register block metadata collection for manifest-based block registration.
	 */
	private function register_block_metadata_collection(): void {
		wp_register_block_metadata_collection(
			PRC_BLOCK_FORMS_DIR . '/build',
			PRC_BLOCK_FORMS_DIR . '/build/blocks-manifest.php'
		);
	}

	/**
	 * Initialize plugin components.
	 */
	private function init_dependencies(): void {
		new Forms( $this->loader );
		new Form_Sample_Provisioner( $this->loader );
		new Form_Send_Email( $this->loader );
		new Form_Response_Log( $this->loader );
		new Abilities( $this->loader );

		if ( class_exists( Synced_Form::class ) ) {
			new Synced_Form( $this->loader );
		}

		if ( class_exists( Form::class ) ) {
			new Form( $this->loader );
		}
		if ( class_exists( Form_Captcha::class ) ) {
			new Form_Captcha( $this->loader );
		}
		if ( class_exists( Form_Message::class ) ) {
			new Form_Message( $this->loader );
		}
		if ( class_exists( Form_Page::class ) ) {
			new Form_Page( $this->loader );
		}
		if ( class_exists( Form_Submit::class ) ) {
			new Form_Submit( $this->loader );
		}

		$this->loader->add_filter( 'prc_platform_form_endpoints', $this, 'register_default_form_endpoints' );
	}

	/**
	 * Register default form endpoint metadata for the prc-block/form block editor.
	 *
	 * @param array<int, array<string, string>> $endpoints Existing endpoints.
	 * @return array<int, array<string, string>>
	 */
	public function register_default_form_endpoints( array $endpoints ): array {
		$endpoints[] = array(
			'namespace'   => 'prc-block-library/forms',
			'action'      => 'email',
			'method'      => 'api',
			'label'       => 'Email',
			'description' => 'Email form',
		);

		return $endpoints;
	}

	public function run(): void {
		$this->loader->run();
	}

	public function get_plugin_name(): string {
		return $this->plugin_name;
	}

	public function get_loader(): Loader {
		return $this->loader;
	}

	public function get_version(): string {
		return $this->version;
	}
}
