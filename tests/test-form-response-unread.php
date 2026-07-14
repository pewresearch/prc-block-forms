<?php
/**
 * Unit-level coverage for unread defaults in Form_Response_Repository insert formatting helpers.
 *
 * Full DB insert/query coverage requires WordPress; this file locks the format_row unread
 * semantics and set_unread empty-id guard via reflection/stubs where possible.
 *
 * Run with:
 *   php plugins/prc-block-forms/tests/test-form-response-unread.php
 */

declare(strict_types=1);

namespace {

	$GLOBALS['__failed'] = 0;

	function assert_true( bool $condition, string $message ): void {
		if ( $condition ) {
			echo "PASS: {$message}\n";
			return;
		}
		echo "FAIL: {$message}\n";
		++$GLOBALS['__failed'];
	}

	// Minimal stubs so the repository class can load without WP.
	function absint( $v ): int {
		return abs( (int) $v );
	}
	function wp_parse_args( $args, $defaults ): array {
		return array_merge( $defaults, $args );
	}
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
	function current_time( string $type, bool $gmt = false ): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	require_once dirname( __DIR__ ) . '/includes/class-form-response-schema.php';
	require_once dirname( __DIR__ ) . '/includes/class-form-response-repository.php';

	$repo   = new \PRC\Platform\Block_Forms\Form_Response_Repository();
	$method = new ReflectionMethod( $repo, 'format_row' );
	$method->setAccessible( true );

	$row = $method->invoke(
		$repo,
		array(
			'id'             => 1,
			'created_at'     => '2026-07-12 00:00:00',
			'form_id'        => 10,
			'form_name'      => 'Contact',
			'action'         => 'logResponse',
			'status'         => 'received',
			'email'          => 'a@example.com',
			'from_name'      => 'A',
			'fields'         => '[]',
			'source_url'     => '',
			'source_post_id' => 0,
			'user_id'        => 0,
			'user_agent'     => '',
			'is_spam'        => 0,
			'is_unread'      => 1,
		)
	);
	assert_true( true === $row['is_unread'], 'format_row unread=1 => true' );

	$row_read = $method->invoke(
		$repo,
		array(
			'id'             => 2,
			'created_at'     => '2026-07-12 00:00:00',
			'form_id'        => 10,
			'form_name'      => 'Contact',
			'action'         => 'logResponse',
			'status'         => 'received',
			'email'          => null,
			'from_name'      => null,
			'fields'         => null,
			'source_url'     => null,
			'source_post_id' => null,
			'user_id'        => null,
			'user_agent'     => null,
			'is_spam'        => 0,
			'is_unread'      => 0,
		)
	);
	assert_true( false === $row_read['is_unread'], 'format_row unread=0 => false' );

	// Missing column (pre-migration row) defaults to unread.
	$row_legacy = $method->invoke(
		$repo,
		array(
			'id'             => 3,
			'created_at'     => '2026-07-12 00:00:00',
			'form_id'        => null,
			'form_name'      => null,
			'action'         => 'logResponse',
			'status'         => 'received',
			'email'          => null,
			'from_name'      => null,
			'fields'         => null,
			'source_url'     => null,
			'source_post_id' => null,
			'user_id'        => null,
			'user_agent'     => null,
			'is_spam'        => 0,
		)
	);
	assert_true( true === $row_legacy['is_unread'], 'missing is_unread defaults unread' );

	assert_true( 0 === $repo->set_unread( array(), false ), 'empty ids updates 0 rows' );
	assert_true( 0 === $repo->set_spam( array(), true ), 'empty spam ids updates 0 rows' );

	$schema = new \PRC\Platform\Block_Forms\Form_Response_Schema();
	assert_true( '3' === \PRC\Platform\Block_Forms\Form_Response_Schema::SCHEMA_VERSION, 'schema version is 3' );

	$bound = new ReflectionMethod( $repo, 'normalize_datetime_bound' );
	$bound->setAccessible( true );
	assert_true( is_string( $bound->invoke( $repo, '2026-07-01T12:00:00Z' ) ), 'normalizes ISO datetime' );
	assert_true( null === $bound->invoke( $repo, 'not-a-date' ), 'rejects invalid datetime' );

	if ( $GLOBALS['__failed'] > 0 ) {
		fwrite( STDERR, "\n{$GLOBALS['__failed']} failure(s)\n" );
		exit( 1 );
	}

	echo "\nAll unread helper tests passed.\n";
	exit( 0 );
}
