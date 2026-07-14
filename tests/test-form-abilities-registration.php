<?php
/**
 * Smoke tests for form ability registration metadata helpers.
 *
 * Run with:
 *   php plugins/prc-block-forms/tests/test-form-abilities-registration.php
 */

declare(strict_types=1);

namespace {

	$GLOBALS['__failed']     = 0;
	$GLOBALS['__registered'] = array();

	function assert_true( bool $condition, string $message ): void {
		if ( $condition ) {
			echo "PASS: {$message}\n";
			return;
		}
		echo "FAIL: {$message}\n";
		++$GLOBALS['__failed'];
	}

	function wp_register_ability( string $name, array $args ) {
		$GLOBALS['__registered'][ $name ] = $args;
		return true;
	}

	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}

	class Fake_Loader {
		public array $actions = array();

		public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
			$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
		}
	}

	require_once dirname( __DIR__ ) . '/includes/abilities/class-form-abilities.php';
	require_once dirname( __DIR__ ) . '/includes/abilities/class-form-response-abilities.php';

	$loader = new Fake_Loader();
	$forms  = new \PRC\Platform\Block_Forms\Form_Abilities( $loader );
	$resps  = new \PRC\Platform\Block_Forms\Form_Response_Abilities( $loader );

	$forms->register_abilities();
	$resps->register_abilities();

	$expected = array(
		'prc-block-forms/list-forms',
		'prc-block-forms/get-form',
		'prc-block-forms/create-form',
		'prc-block-forms/delete-form',
		'prc-block-forms/get-responses',
		'prc-block-forms/update-response',
		'prc-block-forms/bulk-update-responses',
		'prc-block-forms/get-status-counts',
	);

	foreach ( $expected as $name ) {
		assert_true( isset( $GLOBALS['__registered'][ $name ] ), "registered {$name}" );
		$meta = $GLOBALS['__registered'][ $name ]['meta'] ?? array();
		assert_true( ! empty( $meta['show_in_rest'] ), "{$name} show_in_rest" );
		assert_true( ! empty( $meta['mcp']['public'] ), "{$name} mcp public" );
	}

	assert_true(
		true === ( $GLOBALS['__registered']['prc-block-forms/list-forms']['meta']['annotations']['readonly'] ?? false ),
		'list-forms is readonly'
	);
	assert_true(
		true === ( $GLOBALS['__registered']['prc-block-forms/delete-form']['meta']['annotations']['destructive'] ?? false ),
		'delete-form is destructive'
	);
	assert_true(
		true === ( $GLOBALS['__registered']['prc-block-forms/update-response']['meta']['annotations']['destructive'] ?? false ),
		'update-response is destructive (trash hard-deletes)'
	);
	assert_true(
		false === ( $GLOBALS['__registered']['prc-block-forms/update-response']['meta']['annotations']['idempotent'] ?? true ),
		'update-response is not idempotent'
	);
	assert_true(
		in_array(
			'mark_as_read',
			$GLOBALS['__registered']['prc-block-forms/bulk-update-responses']['input_schema']['properties']['action']['enum'] ?? array(),
			true
		),
		'bulk-update includes mark_as_read'
	);

	if ( $GLOBALS['__failed'] > 0 ) {
		fwrite( STDERR, "\n{$GLOBALS['__failed']} failure(s)\n" );
		exit( 1 );
	}

	echo "\nAll form ability registration tests passed.\n";
	exit( 0 );
}
