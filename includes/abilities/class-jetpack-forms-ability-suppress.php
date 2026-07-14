<?php
/**
 * Disable Jetpack Forms abilities so agents use PRC block-forms tools instead.
 *
 * @package PRC\Platform\Block_Forms
 */

declare(strict_types=1);

namespace PRC\Platform\Block_Forms;

/**
 * Unregisters jetpack-forms/* abilities and keeps them off REST/MCP if re-registered.
 */
class Jetpack_Forms_Ability_Suppress {

	/**
	 * Jetpack form ability slugs observed on VIP (without namespace).
	 *
	 * @var string[]
	 */
	const DEFAULT_ABILITY_SLUGS = array(
		'list-forms',
		'get-form',
		'create-form',
		'delete-form',
		'get-responses',
		'update-response',
		'bulk-update-responses',
		'get-status-counts',
	);

	/**
	 * Hook suppressors.
	 *
	 * @param Loader $loader Plugin loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'wp_abilities_api_init', $this, 'unregister_jetpack_form_abilities', 100 );
		$loader->add_filter( 'wp_register_ability_args', $this, 'hide_jetpack_form_ability_from_mcp', 10, 2 );
	}

	/**
	 * Ability names to disable (filterable).
	 *
	 * @return string[] Fully-qualified ability names.
	 */
	public static function get_disabled_ability_names(): array {
		$slugs = apply_filters( 'prc_block_forms_disabled_jetpack_abilities', self::DEFAULT_ABILITY_SLUGS );
		if ( ! is_array( $slugs ) ) {
			$slugs = self::DEFAULT_ABILITY_SLUGS;
		}

		$names = array();
		foreach ( $slugs as $slug ) {
			$slug = (string) $slug;
			if ( '' === $slug ) {
				continue;
			}
			if ( str_starts_with( $slug, 'jetpack-forms/' ) ) {
				$names[] = $slug;
				continue;
			}
			$names[] = 'jetpack-forms/' . ltrim( $slug, '/' );
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * Unregister known Jetpack form abilities after they register.
	 *
	 * @hook wp_abilities_api_init
	 */
	public function unregister_jetpack_form_abilities(): void {
		if ( ! function_exists( 'wp_unregister_ability' ) || ! function_exists( 'wp_has_ability' ) ) {
			return;
		}

		foreach ( self::get_disabled_ability_names() as $name ) {
			if ( wp_has_ability( $name ) ) {
				wp_unregister_ability( $name );
			}
		}
	}

	/**
	 * If Jetpack re-registers later, keep abilities off REST/MCP discovery.
	 *
	 * @param array  $args Ability args.
	 * @param string $name Ability name.
	 * @return array
	 *
	 * @hook wp_register_ability_args
	 */
	public function hide_jetpack_form_ability_from_mcp( $args, $name ) {
		if ( ! is_string( $name ) || ! str_starts_with( $name, 'jetpack-forms/' ) ) {
			return $args;
		}

		if ( ! is_array( $args ) ) {
			$args = array();
		}

		if ( ! isset( $args['meta'] ) || ! is_array( $args['meta'] ) ) {
			$args['meta'] = array();
		}

		$args['meta']['show_in_rest'] = false;
		unset( $args['meta']['mcp'] );

		return $args;
	}
}
