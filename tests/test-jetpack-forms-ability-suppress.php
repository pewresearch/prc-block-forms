<?php
/**
 * Smoke tests for Jetpack Forms ability suppression.
 *
 * Run with:
 *   php plugins/prc-block-forms/tests/test-jetpack-forms-ability-suppress.php
 */

declare(strict_types=1);

namespace {

	$GLOBALS['__failed']    = 0;
	$GLOBALS['__abilities']  = array();
	$GLOBALS['__filters']    = array();

	function assert_true( bool $condition, string $message ): void {
		if ( $condition ) {
			echo "PASS: {$message}\n";
			return;
		}
		echo "FAIL: {$message}\n";
		++$GLOBALS['__failed'];
	}

	function wp_has_ability( string $name ): bool {
		return isset( $GLOBALS['__abilities'][ $name ] );
	}

	function wp_unregister_ability( string $name ) {
		if ( ! isset( $GLOBALS['__abilities'][ $name ] ) ) {
			return null;
		}
		$ability = $GLOBALS['__abilities'][ $name ];
		unset( $GLOBALS['__abilities'][ $name ] );
		return $ability;
	}

	function apply_filters( string $hook, $value ) {
		if ( 'prc_block_forms_disabled_jetpack_abilities' === $hook && isset( $GLOBALS['__filters'][ $hook ] ) ) {
			return call_user_func( $GLOBALS['__filters'][ $hook ], $value );
		}
		return $value;
	}

	class Fake_Loader {
		public array $actions = array();
		public array $filters = array();

		public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
			$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
		}

		public function add_filter( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
			$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
		}
	}

	require_once dirname( __DIR__ ) . '/includes/abilities/class-jetpack-forms-ability-suppress.php';

	$loader    = new Fake_Loader();
	$suppress  = new \PRC\Platform\Block_Forms\Jetpack_Forms_Ability_Suppress( $loader );

	assert_true( 1 === count( $loader->actions ), 'registers wp_abilities_api_init action' );
	assert_true( 100 === $loader->actions[0]['priority'], 'unregister runs at priority 100' );
	assert_true( 1 === count( $loader->filters ), 'registers wp_register_ability_args filter' );
	assert_true( 2 === $loader->filters[0]['accepted_args'], 'filter accepts 2 args' );

	$names = \PRC\Platform\Block_Forms\Jetpack_Forms_Ability_Suppress::get_disabled_ability_names();
	assert_true( in_array( 'jetpack-forms/list-forms', $names, true ), 'includes list-forms' );
	assert_true( 8 === count( $names ), 'default set has eight abilities' );

	$GLOBALS['__filters']['prc_block_forms_disabled_jetpack_abilities'] = static function () {
		return array( 'custom-tool', 'jetpack-forms/already-prefixed' );
	};
	$filtered = \PRC\Platform\Block_Forms\Jetpack_Forms_Ability_Suppress::get_disabled_ability_names();
	assert_true( in_array( 'jetpack-forms/custom-tool', $filtered, true ), 'filter can add slugs' );
	assert_true( in_array( 'jetpack-forms/already-prefixed', $filtered, true ), 'filter keeps prefixed names' );
	unset( $GLOBALS['__filters']['prc_block_forms_disabled_jetpack_abilities'] );

	$GLOBALS['__abilities'] = array(
		'jetpack-forms/list-forms' => (object) array( 'name' => 'jetpack-forms/list-forms' ),
		'other/keep-me'            => (object) array( 'name' => 'other/keep-me' ),
	);
	$suppress->unregister_jetpack_form_abilities();
	assert_true( ! wp_has_ability( 'jetpack-forms/list-forms' ), 'unregisters jetpack list-forms' );
	assert_true( wp_has_ability( 'other/keep-me' ), 'leaves unrelated abilities alone' );

	// Missing Jetpack: no-op, no fatal.
	$GLOBALS['__abilities'] = array();
	$suppress->unregister_jetpack_form_abilities();
	assert_true( true, 'missing Jetpack abilities is a no-op' );

	$args = $suppress->hide_jetpack_form_ability_from_mcp(
		array(
			'meta' => array(
				'show_in_rest' => true,
				'mcp'          => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		),
		'jetpack-forms/list-forms'
	);
	assert_true( false === $args['meta']['show_in_rest'], 'hide filter clears show_in_rest' );
	assert_true( ! isset( $args['meta']['mcp'] ), 'hide filter removes mcp meta' );

	$passthrough = $suppress->hide_jetpack_form_ability_from_mcp(
		array( 'meta' => array( 'show_in_rest' => true ) ),
		'prc-block-forms/list-forms'
	);
	assert_true( true === $passthrough['meta']['show_in_rest'], 'non-jetpack abilities unchanged' );

	if ( $GLOBALS['__failed'] > 0 ) {
		fwrite( STDERR, "\n{$GLOBALS['__failed']} failure(s)\n" );
		exit( 1 );
	}

	echo "\nAll Jetpack suppress tests passed.\n";
	exit( 0 );
}
