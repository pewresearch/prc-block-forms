<?php
/**
 * Synced Form Block
 *
 * @package PRC\Platform\Block_Forms
 */

namespace PRC\Platform\Block_Forms;

/**
 * Block Name:        Synced Form
 * Description:       Embed a form from the `form` post type anywhere on the site.
 * Requires at least: 6.7
 * Requires PHP:      8.1
 *
 * @package PRC\Platform\Block_Forms
 */
class Synced_Form {
	/**
	 * Constructor
	 *
	 * @param mixed $loader Loader.
	 */
	public function __construct( $loader ) {
		$this->init( $loader );
	}

	/**
	 * Initialize the block
	 *
	 * @param mixed $loader Loader.
	 */
	public function init( $loader = null ) {
		if ( null !== $loader ) {
			$loader->add_action( 'init', $this, 'block_init' );
		}
	}

	/**
	 * Render block callback
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content Block content.
	 * @return string Block content.
	 */
	public function render_block_callback( $attributes, $content ) {
		if ( empty( $attributes['ref'] ) ) {
			return '';
		}

		$content = Form_Renderer::render_by_id( absint( $attributes['ref'] ) );

		if ( ! empty( $attributes['align'] ) ) {
			$content = sprintf(
				'<div %1$s>%2$s</div>',
				get_block_wrapper_attributes(),
				$content
			);
		}

		return $content;
	}

	/**
	 * Registers the block using the metadata loaded from the `block.json` file.
	 *
	 * @hook init
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_block_type/
	 */
	public function block_init() {
		register_block_type_from_metadata(
			PRC_BLOCK_FORMS_DIR . '/build/synced-form',
			array(
				'render_callback' => array( $this, 'render_block_callback' ),
			)
		);
	}
}
