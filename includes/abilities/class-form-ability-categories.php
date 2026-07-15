<?php
/**
 * Forms ability category registration.
 *
 * @package PRC\Platform\Block_Forms
 */

declare(strict_types=1);

namespace PRC\Platform\Block_Forms;

/**
 * Registers the Forms ability category for PRC block form tools.
 */
class Form_Ability_Categories {

	/**
	 * Ability category slug used by prc-block-forms/* abilities.
	 */
	public const CATEGORY = 'forms';

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Plugin loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'wp_abilities_api_categories_init', $this, 'register_categories' );
	}

	/**
	 * Register the Forms ability category.
	 *
	 * @hook wp_abilities_api_categories_init
	 */
	public function register_categories(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( self::CATEGORY ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Forms', 'prc-block-forms' ),
				'description' => __( 'Abilities for managing PRC block forms and their responses.', 'prc-block-forms' ),
			)
		);
	}
}
