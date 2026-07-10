<?php
/**
 * Forms content type and response logging.
 *
 * @package PRC\Platform\Block_Forms
 */

namespace PRC\Platform\Block_Forms;

/**
 * Registers the `form` custom post type — the management hub for reusable,
 * embeddable forms — and boots the form response logging stack (custom table
 * schema, repository, REST controller, and DataViews admin page).
 *
 * @package PRC\Platform\Block_Forms
 */
class Forms {
	/**
	 * The form post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = 'form';

	/**
	 * Constructor.
	 *
	 * @param \PRC\Platform\Block_Forms\Loader $loader The loader.
	 */
	public function __construct( $loader ) {
		require_once __DIR__ . '/class-form-response-schema.php';
		require_once __DIR__ . '/class-form-response-repository.php';
		require_once __DIR__ . '/class-form-response-retention.php';
		require_once __DIR__ . '/class-form-responses-rest-controller.php';
		require_once __DIR__ . '/class-form-responses-assets.php';
		require_once __DIR__ . '/class-form-responses-admin.php';
		require_once __DIR__ . '/class-form-responses-editor.php';

		$loader->add_action( 'init', $this, 'register_post_type' );
		$loader->add_action( 'admin_init', $this, 'maybe_upgrade_responses_table' );

		new Form_Response_Retention( $loader );
		new Form_Responses_REST_Controller( $loader );
		new Form_Responses_Admin( $loader );
		new Form_Responses_Editor( $loader );
	}

	/**
	 * The capability required to view and manage form responses.
	 *
	 * @return string
	 */
	public static function get_responses_capability() {
		/**
		 * Filter the capability required to view and manage form responses.
		 *
		 * @param string $capability The capability. Default 'manage_options'.
		 */
		return apply_filters( 'prc_form_responses_capability', 'manage_options' );
	}

	/**
	 * Get the labels for the form post type.
	 *
	 * @return array
	 */
	public static function get_labels() {
		return array(
			'name'                  => _x( 'Forms', 'Post Type General Name', 'prc-block-forms' ),
			'singular_name'         => _x( 'Form', 'Post Type Singular Name', 'prc-block-forms' ),
			'menu_name'             => __( 'Forms', 'prc-block-forms' ),
			'name_admin_bar'        => __( 'Form', 'prc-block-forms' ),
			'all_items'             => __( 'All Forms', 'prc-block-forms' ),
			'add_new_item'          => __( 'Add New Form', 'prc-block-forms' ),
			'add_new'               => __( 'Add New', 'prc-block-forms' ),
			'new_item'              => __( 'New Form', 'prc-block-forms' ),
			'edit_item'             => __( 'Edit Form', 'prc-block-forms' ),
			'update_item'           => __( 'Update Form', 'prc-block-forms' ),
			'view_item'             => __( 'View Form', 'prc-block-forms' ),
			'search_items'          => __( 'Search Forms', 'prc-block-forms' ),
			'not_found'             => __( 'Not found', 'prc-block-forms' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'prc-block-forms' ),
			'items_list'            => __( 'Forms list', 'prc-block-forms' ),
			'items_list_navigation' => __( 'Forms list navigation', 'prc-block-forms' ),
			'filter_items_list'     => __( 'Filter Forms list', 'prc-block-forms' ),
		);
	}

	/**
	 * Register the form post type.
	 *
	 * Forms are block-composed definitions (a `prc-block/form` block plus its
	 * input blocks) that render only where embedded via `prc-block/synced-form`,
	 * so the post type is not publicly queryable.
	 *
	 * @hook init
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'               => __( 'Form', 'prc-block-forms' ),
				'description'         => __( 'A store for form blocks. Save a form once, embed it anywhere with the Synced Form block, and review its responses centrally.', 'prc-block-forms' ),
				'labels'              => self::get_labels(),
				'supports'            => array( 'title', 'editor', 'author', 'custom-fields', 'revisions' ),
				'hierarchical'        => false,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-feedback',
				'menu_position'       => 62,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => true,
				'can_export'          => true,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'template'            => array( array( 'prc-block/form' ) ),
			)
		);

		// Opt the form CPT into the Presence API so the synced-form block can
		// detect when someone else has the form open and gate polling on it.
		if ( function_exists( 'wp_presence_post_room' ) ) {
			add_post_type_support( self::POST_TYPE, 'presence' );
		}
	}

	/**
	 * Ensure the form responses table matches the current schema version.
	 *
	 * @hook admin_init
	 */
	public function maybe_upgrade_responses_table() {
		( new Form_Response_Schema() )->maybe_upgrade_table();
	}
}
