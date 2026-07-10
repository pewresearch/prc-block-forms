<?php
/**
 * Utility functions for PRC Block Forms.
 *
 * @package PRC\Platform\Block_Forms
 */

declare(strict_types=1);

/**
 * Load block metadata for a block from the prc-block-forms build manifest.
 *
 * @param string $block_name Block directory slug (e.g. `form-message-bindings`).
 * @return array<string, mixed> Block metadata with a normalized `file` path, or empty array.
 */
function prc_block_forms_manifest( string $block_name ): array {
	$manifest_path = PRC_BLOCK_FORMS_DIR . '/build/blocks-manifest.php';
	if ( ! file_exists( $manifest_path ) ) {
		return array();
	}

	$manifest = include $manifest_path;
	if ( ! isset( $manifest[ $block_name ] ) ) {
		return array();
	}

	$block_manifest = $manifest[ $block_name ];
	$block_json     = PRC_BLOCK_FORMS_DIR . '/build/' . $block_name . '/block.json';
	if ( file_exists( $block_json ) ) {
		$block_manifest['file'] = wp_normalize_path( realpath( $block_json ) );
	}

	return $block_manifest;
}
