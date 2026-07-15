<?php
/**
 * Bootstrap WordPress Abilities for PRC block forms.
 *
 * @package PRC\Platform\Block_Forms
 */

declare(strict_types=1);

namespace PRC\Platform\Block_Forms;

/**
 * Loads form/response ability registrars and Jetpack suppression.
 */
class Abilities {

	/**
	 * Constructor.
	 *
	 * @param Loader $loader Plugin loader.
	 */
	public function __construct( $loader ) {
		require_once __DIR__ . '/class-form-ability-categories.php';
		require_once __DIR__ . '/class-jetpack-forms-ability-suppress.php';
		require_once __DIR__ . '/class-form-abilities.php';
		require_once __DIR__ . '/class-form-response-abilities.php';

		new Form_Ability_Categories( $loader );
		new Jetpack_Forms_Ability_Suppress( $loader );
		new Form_Abilities( $loader );
		new Form_Response_Abilities( $loader );
	}
}
