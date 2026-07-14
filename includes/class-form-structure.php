<?php
/**
 * Parse form CPT block content into a field/structure summary for agents.
 *
 * @package PRC\Platform\Block_Forms
 */

declare(strict_types=1);

namespace PRC\Platform\Block_Forms;

/**
 * Extracts form attributes and input field definitions from Gutenberg markup.
 */
class Form_Structure {

	/**
	 * Block names that wrap inputs but are not themselves form fields.
	 *
	 * @var string[]
	 */
	const SKIP_BLOCKS = array(
		'prc-block/form-field',
		'prc-block/form-page',
		'prc-block/form-message',
		'prc-block/form-submit',
		'prc-block/form-captcha',
	);

	/**
	 * Action config keys that must never be returned to agents in cleartext.
	 *
	 * @var string[]
	 */
	const SENSITIVE_ACTION_CONFIG_KEYS = array(
		'forwardTo',
		'forward_to',
	);

	/**
	 * Parse post_content markup into form attrs + fields.
	 *
	 * @param string $content Block markup.
	 * @return array{form: array<string, mixed>, fields: array<int, array<string, mixed>>}
	 */
	public static function from_content( string $content ): array {
		$blocks = function_exists( 'parse_blocks' ) ? parse_blocks( $content ) : array();

		$form   = array(
			'formName'     => '',
			'method'       => '',
			'namespace'    => '',
			'action'       => '',
			'actionConfig' => array(),
			'redirectUrl'  => '',
		);
		$fields = array();

		self::walk_blocks( $blocks, $form, $fields );

		return array(
			'form'   => $form,
			'fields' => $fields,
		);
	}

	/**
	 * Parse a form CPT post by ID.
	 *
	 * @param int $form_id Form post ID.
	 * @return array{form: array<string, mixed>, fields: array<int, array<string, mixed>>}|null
	 */
	public static function from_post_id( int $form_id ): ?array {
		$post = get_post( $form_id );
		if ( ! $post || Forms::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return self::from_content( (string) $post->post_content );
	}

	/**
	 * Redact sensitive actionConfig values for agent-facing output.
	 *
	 * @param array<string, mixed> $action_config Raw actionConfig.
	 * @return array<string, mixed>
	 */
	public static function redact_action_config( array $action_config ): array {
		$redacted = array();
		foreach ( $action_config as $key => $value ) {
			if ( in_array( (string) $key, self::SENSITIVE_ACTION_CONFIG_KEYS, true ) ) {
				$redacted[ $key ] = self::mask_secret( is_scalar( $value ) ? (string) $value : '' );
				continue;
			}
			$redacted[ $key ] = $value;
		}
		return $redacted;
	}

	/**
	 * Return block markup with sensitive actionConfig values redacted.
	 *
	 * Prefer serialize_blocks when available; otherwise scrub JSON attr blobs
	 * in comment delimiters so cleartext forwardTo never leaves the ability layer.
	 *
	 * @param string $content Raw post_content.
	 * @return string
	 */
	public static function redact_content( string $content ): string {
		if ( '' === $content ) {
			return '';
		}

		if ( function_exists( 'parse_blocks' ) && function_exists( 'serialize_blocks' ) ) {
			$blocks = parse_blocks( $content );
			return serialize_blocks( self::redact_blocks( $blocks ) );
		}

		return (string) preg_replace_callback(
			'/("(?:forwardTo|forward_to)"\s*:\s*")((?:\\\\.|[^"\\\\])*)(")/',
			static function ( array $matches ): string {
				return $matches[1] . self::mask_secret( stripcslashes( $matches[2] ) ) . $matches[3];
			},
			$content
		);
	}

	/**
	 * Recursively redact sensitive attrs on a block tree.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array<string, mixed>>
	 */
	private static function redact_blocks( array $blocks ): array {
		foreach ( $blocks as $i => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			if ( isset( $attrs['actionConfig'] ) && is_array( $attrs['actionConfig'] ) ) {
				$attrs['actionConfig'] = self::redact_action_config( $attrs['actionConfig'] );
			}
			foreach ( self::SENSITIVE_ACTION_CONFIG_KEYS as $key ) {
				if ( isset( $attrs[ $key ] ) && is_scalar( $attrs[ $key ] ) ) {
					$attrs[ $key ] = self::mask_secret( (string) $attrs[ $key ] );
				}
			}
			$blocks[ $i ]['attrs'] = $attrs;
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$blocks[ $i ]['innerBlocks'] = self::redact_blocks( $block['innerBlocks'] );
			}
		}
		return $blocks;
	}

	/**
	 * Default empty form block markup (form shell + submit).
	 *
	 * @param string $form_name Optional formName attribute.
	 * @return string
	 */
	public static function empty_form_content( string $form_name = '' ): string {
		$attrs = array(
			'formName'  => $form_name,
			'method'    => 'rest',
			'namespace' => 'prc-block/form',
			'action'    => 'logResponse',
		);
		$json  = wp_json_encode( $attrs );

		return "<!-- wp:prc-block/form {$json} -->\n"
			. '<form class="wp-block-prc-block-form">'
			. '<!-- wp:prc-block/form-submit /-->'
			. '</form>'
			. "\n<!-- /wp:prc-block/form -->";
	}

	/**
	 * Recursively walk blocks collecting form attrs and input fields.
	 *
	 * @param array                $blocks Parsed blocks.
	 * @param array<string, mixed> $form   Form attrs accumulator (by ref).
	 * @param array                $fields Fields accumulator (by ref).
	 */
	private static function walk_blocks( array $blocks, array &$form, array &$fields ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name   = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			$attrs  = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			$inner  = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();

			if ( 'prc-block/form' === $name ) {
				$form['formName']     = isset( $attrs['formName'] ) ? (string) $attrs['formName'] : $form['formName'];
				$form['method']       = isset( $attrs['method'] ) ? (string) $attrs['method'] : $form['method'];
				$form['namespace']    = isset( $attrs['namespace'] ) ? (string) $attrs['namespace'] : $form['namespace'];
				$form['action']       = isset( $attrs['action'] ) ? (string) $attrs['action'] : $form['action'];
				$form['redirectUrl']  = isset( $attrs['redirectUrl'] ) ? (string) $attrs['redirectUrl'] : $form['redirectUrl'];
				$form['actionConfig'] = is_array( $attrs['actionConfig'] ?? null )
					? self::redact_action_config( $attrs['actionConfig'] )
					: array();
			}

			if (
				'' !== $name
				&& str_starts_with( $name, 'prc-block/form-' )
				&& ! in_array( $name, self::SKIP_BLOCKS, true )
				&& str_starts_with( $name, 'prc-block/form-input-' )
			) {
				// parse_blocks() only returns delimiter JSON attrs; label/type/required
				// often live in saved HTML (source: html / source: attribute).
				$attrs    = self::hydrate_html_attrs(
					$attrs,
					isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '',
					$name
				);
				$metadata = is_array( $attrs['metadata'] ?? null ) ? $attrs['metadata'] : array();
				$label    = self::plain_label( isset( $attrs['label'] ) ? (string) $attrs['label'] : '' );
				$field    = array(
					'block'    => $name,
					'name'     => isset( $metadata['name'] ) ? (string) $metadata['name'] : '',
					'label'    => $label,
					'type'     => isset( $attrs['type'] ) ? (string) $attrs['type'] : self::type_from_block_name( $name ),
					'required' => ! empty( $attrs['required'] ),
				);
				if ( ! empty( $attrs['options'] ) && is_array( $attrs['options'] ) ) {
					$field['options'] = array_values(
						array_map(
							static function ( $option ) {
								if ( ! is_array( $option ) ) {
									return array(
										'label' => (string) $option,
										'value' => (string) $option,
									);
								}
								return array(
									'label' => isset( $option['label'] ) ? (string) $option['label'] : '',
									'value' => isset( $option['value'] ) ? (string) $option['value'] : '',
								);
							},
							$attrs['options']
						)
					);
				}
				$fields[] = $field;
			}

			if ( ! empty( $inner ) ) {
				self::walk_blocks( $inner, $form, $fields );
			}
		}
	}

	/**
	 * Hydrate attrs that Gutenberg stores in inner HTML, not delimiter JSON.
	 *
	 * @param array<string, mixed> $attrs      Delimiter attrs from parse_blocks().
	 * @param string               $inner_html Block innerHTML.
	 * @param string               $block_name Block name.
	 * @return array<string, mixed>
	 */
	private static function hydrate_html_attrs( array $attrs, string $inner_html, string $block_name ): array {
		if ( '' === $inner_html ) {
			return $attrs;
		}

		if ( ( ! isset( $attrs['label'] ) || '' === (string) $attrs['label'] )
			&& preg_match( '/<label\b[^>]*>(.*?)<\/label>/is', $inner_html, $matches )
		) {
			$attrs['label'] = $matches[1];
		}

		// Only form-input-text sources `type` from the <input> attribute.
		// Select/checkbox use a non-HTML type enum (do not read type="text" from markup).
		if ( ( ! isset( $attrs['type'] ) || '' === (string) $attrs['type'] )
			&& 'prc-block/form-input-text' === $block_name
			&& preg_match( '/<input\b[^>]*\stype=(["\'])([^"\']+)\1/i', $inner_html, $matches )
		) {
			$attrs['type'] = $matches[2];
		}

		// Select stores required on the control element (source: attribute).
		// Skip quoted attribute values so placeholder="… required …" is not a hit.
		if ( empty( $attrs['required'] )
			&& preg_match( '/<(?:input|textarea|select)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*\srequired(?:\s|=|\/|>)/i', $inner_html )
		) {
			$attrs['required'] = true;
		}

		return $attrs;
	}

	/**
	 * Strip HTML from a RichText label.
	 *
	 * @param string $raw Raw label.
	 * @return string
	 */
	private static function plain_label( string $raw ): string {
		$stripped = wp_strip_all_tags( $raw );
		return trim( html_entity_decode( $stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Derive a short type slug from a form-input block name.
	 *
	 * @param string $block_name Block name.
	 * @return string
	 */
	private static function type_from_block_name( string $block_name ): string {
		return str_replace( 'prc-block/form-input-', '', $block_name );
	}

	/**
	 * Mask a secret for agent output (preserve presence, hide value).
	 *
	 * Never echo substrings of the original secret — short emails like
	 * `a@x.com` would otherwise leak `@` through a prefix/suffix mask.
	 *
	 * @param string $value Cleartext.
	 * @return string
	 */
	private static function mask_secret( string $value ): string {
		return '' === trim( $value ) ? '' : '[redacted]';
	}
}
