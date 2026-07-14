<?php
/**
 * Smoke tests for Form_Structure field extraction + redaction.
 *
 * Run with:
 *   php plugins/prc-block-forms/tests/test-form-structure.php
 */

declare(strict_types=1);

namespace {

	$GLOBALS['__failed'] = 0;

	function wp_json_encode( $data ) {
		return json_encode( $data );
	}

	function wp_strip_all_tags( string $text ): string {
		return strip_tags( $text );
	}

	function parse_blocks( string $content ): array {
		$blocks = array();
		if ( preg_match_all( '/<!--\s*wp:([\w\-\/]+)(\s+(\{.*?\}))?\s+(\/)?-->/s', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$name  = $match[1];
				$attrs = array();
				if ( ! empty( $match[3] ) ) {
					$decoded = json_decode( $match[3], true );
					$attrs   = is_array( $decoded ) ? $decoded : array();
				}
				$blocks[] = array(
					'blockName'   => $name,
					'attrs'       => $attrs,
					'innerBlocks' => array(),
				);
			}
		}

		// Nest non-form blocks under the first form block for a simple tree.
		$form_index = null;
		foreach ( $blocks as $i => $block ) {
			if ( 'prc-block/form' === $block['blockName'] ) {
				$form_index = $i;
				break;
			}
		}
		if ( null !== $form_index ) {
			$inner = array();
			foreach ( $blocks as $i => $block ) {
				if ( $i === $form_index ) {
					continue;
				}
				$inner[] = $block;
			}
			$blocks[ $form_index ]['innerBlocks'] = $inner;
			return array( $blocks[ $form_index ] );
		}

		return $blocks;
	}

	function assert_true( bool $condition, string $message ): void {
		if ( $condition ) {
			echo "PASS: {$message}\n";
			return;
		}
		echo "FAIL: {$message}\n";
		++$GLOBALS['__failed'];
	}

	require_once dirname( __DIR__ ) . '/includes/class-form-structure.php';

	$pattern = file_get_contents( dirname( __DIR__ ) . '/patterns/speaker-request-form.php' );
	$result  = \PRC\Platform\Block_Forms\Form_Structure::from_content( $pattern );

	assert_true( 'Speaker Request Form' === ( $result['form']['formName'] ?? '' ), 'formName extracted' );
	assert_true( 'sendToEmail' === ( $result['form']['action'] ?? '' ), 'action extracted' );
	assert_true( ! empty( $result['form']['actionConfig']['forwardTo'] ), 'forwardTo present (redacted)' );
	assert_true(
		false === strpos( (string) $result['form']['actionConfig']['forwardTo'], '@' ),
		'forwardTo redacted (no @)'
	);

	$names = array_column( $result['fields'], 'name' );
	assert_true( in_array( 'fullName', $names, true ), 'fullName field found' );
	assert_true( in_array( 'email', $names, true ), 'email field found' );
	assert_true( in_array( 'typeOfEvent', $names, true ), 'select field found' );
	assert_true( count( $result['fields'] ) >= 8, 'multiple input fields extracted' );

	$empty = \PRC\Platform\Block_Forms\Form_Structure::from_content( '' );
	assert_true( empty( $empty['fields'] ), 'empty content yields no fields' );

	$nested = \PRC\Platform\Block_Forms\Form_Structure::from_content(
		'<!-- wp:prc-block/form {"formName":"Nested","action":"logResponse"} -->'
		. '<!-- wp:prc-block/form-page -->'
		. '<!-- wp:prc-block/form-input-text {"metadata":{"name":"nestedField"},"label":"Nested"} /-->'
		. '<!-- /wp:prc-block/form-page -->'
		. '<!-- /wp:prc-block/form -->'
	);
	// Flat regex parser nests everything under form; ensure input still found.
	$nested_names = array_column( $nested['fields'], 'name' );
	assert_true( in_array( 'nestedField', $nested_names, true ), 'nested input field found' );

	$redacted = \PRC\Platform\Block_Forms\Form_Structure::redact_action_config(
		array(
			'forwardTo' => 'editor@example.com',
			'cc'        => 'ops@example.com',
		)
	);
	assert_true( false === strpos( $redacted['forwardTo'], '@example.com' ), 'redact_action_config masks forwardTo' );
	assert_true( 'ops@example.com' === $redacted['cc'], 'non-sensitive keys preserved' );
	assert_true( '[redacted]' === $redacted['forwardTo'], 'mask is presence-only token' );

	$short = \PRC\Platform\Block_Forms\Form_Structure::redact_action_config(
		array( 'forwardTo' => 'a@x.com' )
	);
	assert_true( false === strpos( $short['forwardTo'], '@' ), 'short email mask has no @' );

	$raw_content = '<!-- wp:prc-block/form {"formName":"X","actionConfig":{"forwardTo":"secret@example.com"}} /-->';
	$scrubbed    = \PRC\Platform\Block_Forms\Form_Structure::redact_content( $raw_content );
	assert_true( false === strpos( $scrubbed, 'secret@example.com' ), 'redact_content removes cleartext email' );
	assert_true( false !== strpos( $scrubbed, '[redacted]' ), 'redact_content inserts mask token' );

	$content = \PRC\Platform\Block_Forms\Form_Structure::empty_form_content( 'Demo' );
	assert_true( false !== strpos( $content, 'prc-block/form' ), 'empty form content includes form block' );
	assert_true( false !== strpos( $content, 'form-submit' ), 'empty form content includes submit' );

	if ( $GLOBALS['__failed'] > 0 ) {
		fwrite( STDERR, "\n{$GLOBALS['__failed']} failure(s)\n" );
		exit( 1 );
	}

	echo "\nAll Form_Structure tests passed.\n";
	exit( 0 );
}
