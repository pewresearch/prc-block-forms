<?php
/**
 * Seeds sample Forms CPT posts for editor onboarding.
 *
 * @package PRC\Platform\Block_Forms
 */

declare(strict_types=1);

namespace PRC\Platform\Block_Forms;

/**
 * Seeds the sample forms once per site. Deleting a sample never brings it back.
 */
class Form_Sample_Provisioner {

	/**
	 * Per-site flag set the first time sample forms are seeded.
	 */
	const SEEDED_OPTION = 'prc_block_forms_sample_form_seed_version';

	/**
	 * Constructor.
	 *
	 * @param object|null $loader Loader instance for adding hooks.
	 */
	public function __construct( $loader = null ) {
		if ( null !== $loader ) {
			$loader->add_action( 'init', $this, 'maybe_seed_sample_forms', 20 );
		}
	}

	/**
	 * Seed the sample forms if this site has never been seeded.
	 *
	 * @hook init
	 * @return void
	 */
	public function maybe_seed_sample_forms(): void {
		// add_option() fails when the row exists, so concurrent first requests seed only once.
		if ( ! post_type_exists( Forms::POST_TYPE ) || ! add_option( self::SEEDED_OPTION, 1 ) ) {
			return;
		}

		$samples = array(
			array(
				'slug'  => 'speaker-request',
				'title' => __( 'Speaker Request (Sample)', 'prc-block-forms' ),
				'file'  => 'speaker-request-form',
			),
		);

		foreach ( $samples as $sample ) {
			$content = Form_Patterns::get_form_markup( $sample['file'] );
			if ( '' === $content ) {
				continue;
			}

			wp_insert_post(
				array(
					'post_type'    => Forms::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $sample['title'],
					'post_name'    => $sample['slug'],
					'post_content' => $content,
				)
			);
		}
	}
}
