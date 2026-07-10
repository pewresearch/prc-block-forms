<?php
/**
 * Seeds sample Forms CPT posts for editor onboarding.
 *
 * @package PRC\Platform\Block_Forms
 */

declare(strict_types=1);

namespace PRC\Platform\Block_Forms;

/**
 * Idempotently provisions optional sample forms in the Forms CPT.
 */
class Form_Sample_Provisioner {

	/**
	 * Stable slug for the speaker request sample form.
	 */
	const SPEAKER_REQUEST_SLUG = 'speaker-request';

	/**
	 * Option key tracking the last successful sample-form seed version.
	 */
	const SEED_VERSION_OPTION = 'prc_block_forms_sample_form_seed_version';

	/**
	 * Current seed version — bump to force re-provision on init self-heal.
	 */
	const SEED_VERSION = 1;

	/**
	 * Constructor.
	 *
	 * @param object|null $loader Loader instance for adding hooks.
	 */
	public function __construct( $loader = null ) {
		if ( null !== $loader ) {
			$loader->add_action( 'init', $this, 'maybe_provision_sample_forms', 20 );
		}
	}

	/**
	 * Idempotent init self-heal when the seed version is behind.
	 *
	 * @hook init
	 * @return void
	 */
	public function maybe_provision_sample_forms(): void {
		if ( ! post_type_exists( Forms::POST_TYPE ) ) {
			return;
		}

		$stored_version = (int) get_option( self::SEED_VERSION_OPTION, 0 );
		if ( $stored_version >= self::SEED_VERSION && self::all_sample_forms_exist() ) {
			return;
		}

		self::provision_sample_forms();
	}

	/**
	 * Whether every seeded sample form post exists.
	 *
	 * @return bool
	 */
	private static function all_sample_forms_exist(): bool {
		foreach ( self::get_form_definitions() as $definition ) {
			if ( ! self::get_form_post_by_slug( $definition['slug'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Provision sample forms (self-heal entry point).
	 *
	 * @return void
	 */
	public static function provision_sample_forms(): void {
		if ( ! post_type_exists( Forms::POST_TYPE ) ) {
			return;
		}

		foreach ( self::get_form_definitions() as $definition ) {
			self::ensure_form( $definition );
		}

		update_option( self::SEED_VERSION_OPTION, self::SEED_VERSION );
	}

	/**
	 * Sample form definitions keyed by stable slug.
	 *
	 * @return array<int, array{slug: string, title: string, file: string}>
	 */
	public static function get_form_definitions(): array {
		return array(
			array(
				'slug'  => self::SPEAKER_REQUEST_SLUG,
				'title' => __( 'Speaker Request (Sample)', 'prc-block-forms' ),
				'file'  => 'speaker-request-form',
			),
		);
	}

	/**
	 * Look up a seeded sample form post by slug.
	 *
	 * @param string $slug Form post slug.
	 * @return \WP_Post|null
	 */
	public static function get_form_post_by_slug( string $slug ): ?\WP_Post {
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
	 * Insert a sample form CPT post if it does not already exist.
	 *
	 * @param array{slug: string, title: string, file: string} $args Form definition.
	 * @return void
	 */
	private static function ensure_form( array $args ): void {
		if ( self::get_form_post_by_slug( $args['slug'] ) ) {
			return;
		}

		$content = Form_Patterns::get_form_markup( $args['file'] );
		if ( '' === $content ) {
			return;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => Forms::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $args['title'],
				'post_name'    => $args['slug'],
				'post_content' => $content,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return;
		}

		update_post_meta( (int) $post_id, '_prc_form_sample_seed_version', self::SEED_VERSION );
	}
}
