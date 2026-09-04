<?php
/**
 * Registers the Forms list on the shared Admin DataViews shell.
 *
 * @package PRC\Platform\Block_Forms
 */

namespace PRC\Platform\Block_Forms;

/**
 * Registers the Forms list on the shared Admin DataViews shell.
 *
 * Form configuration lives in `prc-block/form` block attributes inside
 * post_content, which SQL cannot filter on. This class mirrors the attributes
 * the list needs into post meta on save, and backfills existing forms in batches.
 */
class Form_List {
	/**
	 * Admin page slug for the DataViews list (stable URL; e2e uses it).
	 */
	const PAGE_SLUG = 'prc-forms-library';

	const SCRIPT_HANDLE = 'prc-block-forms-admin-dataview';

	/**
	 * Registered form action (e.g. `sendToEmail`, `logResponse`).
	 */
	const META_ACTION = '_prc_form_action';

	/**
	 * Submission transport (`rest` or `api`).
	 */
	const META_METHOD = '_prc_form_method';

	/**
	 * Number of input fields in the form.
	 */
	const META_FIELD_COUNT = '_prc_form_field_count';

	/**
	 * Option flag recording that the list-meta backfill finished.
	 */
	const BACKFILL_OPTION = 'prc_form_list_meta_backfill';

	/**
	 * Bump to re-run the backfill after changing what `sync_list_meta()` stores.
	 */
	const BACKFILL_VERSION = '1';

	/**
	 * Forms synced per admin request while backfilling.
	 */
	const BACKFILL_BATCH_SIZE = 50;

	/**
	 * Constructor.
	 *
	 * @param \PRC\Platform\Block_Forms\Loader $loader The loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'prc_wp_admin_dataview_register_lists', $this, 'register_list' );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_provider_assets', 20 );
		$loader->add_filter( 'prc_wp_admin_dataview_localize', $this, 'localize_provider', 10, 2 );
		$loader->add_action( 'save_post_' . Forms::POST_TYPE, $this, 'sync_list_meta', 10, 2 );
		$loader->add_action( 'admin_init', $this, 'maybe_backfill_list_meta' );
	}

	/**
	 * Register the form list with the shared DataViews shell.
	 *
	 * @param object $lists Shared list registry.
	 */
	public function register_list( $lists ) {
		if ( ! is_object( $lists ) || ! method_exists( $lists, 'register' ) ) {
			return;
		}

		$lists->register(
			array(
				'postType'             => Forms::POST_TYPE,
				'pageSlug'             => self::PAGE_SLUG,
				'menuTitle'            => __( 'All Forms', 'prc-block-forms' ),
				'pageTitle'            => __( 'All Forms', 'prc-block-forms' ),
				'restPath'             => '/prc-api/v3/form/library',
				'hideDefaultNewButton' => true,
			)
		);
	}

	/**
	 * The capability that guards the list.
	 *
	 * @return string
	 */
	public static function get_capability() {
		$post_type_object = get_post_type_object( Forms::POST_TYPE );

		if ( $post_type_object && isset( $post_type_object->cap->edit_posts ) ) {
			return (string) $post_type_object->cap->edit_posts;
		}

		return 'edit_posts';
	}

	/**
	 * Add form filter options and flags to the shell boot data.
	 *
	 * @param array  $localize Localized shell data.
	 * @param string $post_type Current post type.
	 * @return array
	 */
	public function localize_provider( $localize, $post_type ) {
		if ( Forms::POST_TYPE !== $post_type ) {
			return $localize;
		}

		$localize['statuses'] = self::get_status_options();
		$localize['forms']    = array(
			'newFormUrl'       => esc_url_raw(
				admin_url( 'post-new.php?post_type=' . Forms::POST_TYPE )
			),
			'canViewResponses' => current_user_can( Forms::get_responses_capability() ),
			'actions'          => self::get_action_options(),
			'methods'          => self::get_method_options(),
		);

		return $localize;
	}

	/**
	 * Enqueue the form provider after the shared shell.
	 *
	 * @param string $hook_suffix Current admin hook.
	 */
	public function enqueue_provider_assets( $hook_suffix ) {
		unset( $hook_suffix );

		if ( ! wp_script_is( 'prc-wp-admin-dataview', 'enqueued' ) ) {
			return;
		}

		$build_dir  = PRC_BLOCK_FORMS_DIR . '/build/admin-dataview/';
		$asset_file = $build_dir . 'index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset  = include $asset_file;
		$handle = self::SCRIPT_HANDLE;

		wp_enqueue_script(
			$handle,
			plugins_url( 'build/admin-dataview/index.js', PRC_BLOCK_FORMS_FILE ),
			array_merge( $asset['dependencies'], array( 'prc-wp-admin-dataview' ) ),
			$asset['version'],
			true
		);

		if ( file_exists( $build_dir . 'style-index.css' ) ) {
			wp_enqueue_style(
				$handle,
				plugins_url( 'build/admin-dataview/style-index.css', PRC_BLOCK_FORMS_FILE ),
				array( 'wp-components' ),
				$asset['version']
			);
		}

		if ( current_user_can( Forms::get_responses_capability() ) ) {
			wp_localize_script(
				$handle,
				'prcFormResponses',
				Form_Responses_Assets::get_localized_data()
			);
		}
	}

	/**
	 * Human labels for the registered form actions.
	 *
	 * Mirrors `registerForm()` in `src/form/register-forms.js`. Create CRM
	 * Contact is owned by `prc-crm` and only appears when that plugin is active.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function get_action_options() {
		$options = array(
			array(
				'value' => 'sendToEmail',
				'label' => __( 'Contact Form', 'prc-block-forms' ),
			),
			array(
				'value' => 'logResponse',
				'label' => __( 'Save Response', 'prc-block-forms' ),
			),
			array(
				'value' => 'subscribe',
				'label' => __( 'Mailchimp Subscribe', 'prc-block-forms' ),
			),
			array(
				'value' => 'subscribeSelect',
				'label' => __( 'Newsletter Selection (Mailchimp)', 'prc-block-forms' ),
			),
			array(
				'value' => 'sendSystemEmail',
				'label' => __( 'System Email', 'prc-block-forms' ),
			),
		);

		if ( self::is_crm_plugin_active() ) {
			$options[] = array(
				'value' => 'createContact',
				'label' => __( 'Create CRM Contact', 'prc-block-forms' ),
			);
		}

		return $options;
	}

	/**
	 * Whether PRC CRM is active so Create CRM Contact can appear in All Forms.
	 *
	 * @return bool
	 */
	public static function is_crm_plugin_active() {
		if ( defined( 'PRC_CRM_FILE' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			if ( ! defined( 'ABSPATH' ) ) {
				return false;
			}
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'prc-crm/prc-crm.php' );
	}

	/**
	 * Submission transport filter options.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function get_method_options() {
		return array(
			array(
				'value' => 'rest',
				'label' => __( 'REST', 'prc-block-forms' ),
			),
			array(
				'value' => 'api',
				'label' => __( 'API', 'prc-block-forms' ),
			),
		);
	}

	/**
	 * Post status filter options.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function get_status_options() {
		return array(
			array(
				'value' => 'publish',
				'label' => __( 'Published', 'prc-block-forms' ),
			),
			array(
				'value' => 'draft',
				'label' => __( 'Draft', 'prc-block-forms' ),
			),
			array(
				'value' => 'pending',
				'label' => __( 'Pending Review', 'prc-block-forms' ),
			),
			array(
				'value' => 'private',
				'label' => __( 'Private', 'prc-block-forms' ),
			),
			array(
				'value' => 'trash',
				'label' => __( 'Trash', 'prc-block-forms' ),
			),
		);
	}

	/**
	 * Mirror `prc-block/form` attributes into post meta so the list can filter
	 * and sort on them in SQL.
	 *
	 * @hook save_post_form
	 *
	 * @param int      $post_id The post ID.
	 * @param \WP_Post $post    The post object.
	 */
	public function sync_list_meta( $post_id, $post = null ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $post instanceof \WP_Post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		self::store_list_meta( (int) $post_id, self::parse_list_meta( (string) $post->post_content ) );
	}

	/**
	 * Parse the list-relevant configuration out of form block markup.
	 *
	 * @param string $content Post content.
	 * @return array{action: string, method: string, field_count: int}
	 */
	public static function parse_list_meta( string $content ) {
		$structure = Form_Structure::from_content( $content );

		return array(
			'action'      => (string) ( $structure['form']['action'] ?? '' ),
			'method'      => (string) ( $structure['form']['method'] ?? '' ),
			'field_count' => count( $structure['fields'] ?? array() ),
		);
	}

	/**
	 * Persist parsed list meta for a form.
	 *
	 * @param int   $post_id The post ID.
	 * @param array $parsed  Output of `parse_list_meta()`.
	 */
	public static function store_list_meta( int $post_id, array $parsed ) {
		update_post_meta( $post_id, self::META_ACTION, (string) $parsed['action'] );
		update_post_meta( $post_id, self::META_METHOD, (string) $parsed['method'] );
		update_post_meta( $post_id, self::META_FIELD_COUNT, (int) $parsed['field_count'] );
	}

	/**
	 * Backfill list meta for forms saved before this class existed.
	 *
	 * Runs a bounded batch per admin request and records completion in an
	 * option, so filters cover every form without a manual CLI step.
	 *
	 * @hook admin_init
	 */
	public function maybe_backfill_list_meta() {
		if ( ! current_user_can( self::get_capability() ) ) {
			return;
		}

		self::run_backfill_batch();
	}

	/**
	 * Sync list meta for one batch of forms that are missing it.
	 *
	 * @return int Number of forms synced.
	 */
	public static function run_backfill_batch() {
		if ( self::BACKFILL_VERSION === get_option( self::BACKFILL_OPTION ) ) {
			return 0;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => Forms::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => self::BACKFILL_BATCH_SIZE,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'             => array(
					array(
						'key'     => self::META_ACTION,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		if ( empty( $query->posts ) ) {
			update_option( self::BACKFILL_OPTION, self::BACKFILL_VERSION, false );
			return 0;
		}

		$synced = 0;

		foreach ( $query->posts as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			self::store_list_meta( (int) $post_id, self::parse_list_meta( (string) $post->post_content ) );
			++$synced;
		}

		return $synced;
	}
}
