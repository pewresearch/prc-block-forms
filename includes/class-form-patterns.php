<?php
/**
 * Block markup patterns for seeded sample forms.
 *
 * @package PRC\Platform\Block_Forms
 */

declare(strict_types=1);

namespace PRC\Platform\Block_Forms;

/**
 * Loads serialized block markup from plugin pattern files.
 */
class Form_Patterns {

	/**
	 * Load serialized block markup for a sample form pattern.
	 *
	 * @param string $name Pattern file basename without extension (e.g. `speaker-request-form`).
	 * @return string
	 */
	public static function get_form_markup( string $name ): string {
		$path = PRC_BLOCK_FORMS_DIR . '/patterns/' . $name . '.php';
		if ( ! is_readable( $path ) ) {
			return '';
		}

		return (string) file_get_contents( $path );
	}
}
