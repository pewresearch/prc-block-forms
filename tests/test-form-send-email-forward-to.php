<?php
/**
 * Coverage for sendToEmail forwardTo resolution (HMAC + form CPT).
 *
 * Run with:
 *   php plugins/prc-block-forms/tests/test-form-send-email-forward-to.php
 */

declare(strict_types=1);

namespace PRC\Platform\Block_Forms {
	/**
	 * Minimal Forms stub so Form_Send_Email can read POST_TYPE without loading the full class.
	 */
	class Forms {
		const POST_TYPE = 'form';
	}
}

namespace {

	if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
		define( 'MINUTE_IN_SECONDS', 60 );
	}
	if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
		define( 'HOUR_IN_SECONDS', 3600 );
	}

	$GLOBALS['__posts']  = array();
	$GLOBALS['__failed'] = 0;

	function wp_salt( string $scheme = 'auth' ): string { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.schemeFound
		return 'test-auth-salt-for-forward-to';
	}

	function sanitize_email( string $email ): string {
		$email = strtolower( trim( $email ) );
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
	}

	function absint( $value ): int {
		return abs( (int) $value );
	}

	function get_post( $post_id ) {
		return $GLOBALS['__posts'][ (int) $post_id ] ?? null;
	}

	function is_user_logged_in(): bool {
		return ! empty( $GLOBALS['__logged_in'] );
	}

	function is_preview(): bool {
		return ! empty( $GLOBALS['__is_preview'] );
	}

	function parse_blocks( string $content ): array {
		if ( preg_match( '/<!--\s*wp:prc-block\/form\s+(\{.*?\})\s*(?:\/)?-->/s', $content, $m ) ) {
			$attrs = json_decode( $m[1], true );
			return array(
				array(
					'blockName'   => 'prc-block/form',
					'attrs'       => is_array( $attrs ) ? $attrs : array(),
					'innerBlocks' => array(),
				),
			);
		}
		return array();
	}

	function is_wp_error( $thing ): bool {
		return $thing instanceof \WP_Error;
	}

	if ( ! class_exists( 'WP_Error', false ) ) {
		class WP_Error {
			public function __construct(
				public string $code = '',
				public string $message = '',
				public mixed $data = null
			) {}

			public function get_error_code(): string {
				return $this->code;
			}
		}
	}

	if ( ! class_exists( 'WP_Post', false ) ) {
		class WP_Post {
			public int $ID = 0;
			public string $post_type = '';
			public string $post_status = '';
			public string $post_content = '';
		}
	}

	function assert_true( bool $cond, string $label ): void {
		if ( $cond ) {
			echo "PASS: {$label}\n";
			return;
		}
		++$GLOBALS['__failed'];
		echo "FAIL: {$label}\n";
	}

	function assert_eq( mixed $expected, mixed $actual, string $label ): void {
		if ( $expected === $actual ) {
			echo "PASS: {$label}\n";
			return;
		}
		++$GLOBALS['__failed'];
		echo 'FAIL: ' . $label . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ")\n";
	}

	require dirname( __DIR__ ) . '/includes/class-form-send-email.php';

	use PRC\Platform\Block_Forms\Form_Send_Email;

	// --- HMAC sign / verify ---
	$email = 'editor@example.com';
	$sig   = Form_Send_Email::sign_forward_to( $email );
	assert_true( '' !== $sig, 'sign_forward_to returns non-empty digest' );
	assert_true( Form_Send_Email::verify_forward_to_signature( $email, $sig ), 'verify accepts matching signature' );
	assert_true(
		Form_Send_Email::verify_forward_to_signature( strtoupper( $email ), $sig ),
		'verify is case-insensitive on email'
	);
	assert_true(
		! Form_Send_Email::verify_forward_to_signature( 'attacker@evil.example', $sig ),
		'verify rejects tampered email'
	);
	assert_true(
		! Form_Send_Email::verify_forward_to_signature( $email, 'deadbeef' ),
		'verify rejects bad signature'
	);

	// --- Inline resolve: unsigned / bad sig rejected ---
	$unsigned = Form_Send_Email::resolve_forward_to(
		array(
			'formPostId'   => 0,
			'actionConfig' => array( 'forwardTo' => $email ),
		)
	);
	assert_true( is_wp_error( $unsigned ), 'inline without sig returns WP_Error' );
	assert_eq( 'invalid_forward_to_signature', $unsigned->get_error_code(), 'unsigned error code' );

	$bad_sig = Form_Send_Email::resolve_forward_to(
		array(
			'formPostId'   => 0,
			'actionConfig' => array(
				'forwardTo'    => $email,
				'forwardToSig' => 'not-a-real-sig',
			),
		)
	);
	assert_true( is_wp_error( $bad_sig ), 'inline with bad sig returns WP_Error' );

	$good_inline = Form_Send_Email::resolve_forward_to(
		array(
			'formPostId'   => 0,
			'actionConfig' => array(
				'forwardTo'    => $email,
				'forwardToSig' => $sig,
			),
		)
	);
	assert_eq( $email, $good_inline, 'inline with valid sig resolves recipient' );

	// --- CPT path: ignore client forwardTo ---
	$form_id = 42;
	$stored  = 'inbox@pewresearch.example';
	$post    = new \WP_Post();
	$post->ID           = $form_id;
	$post->post_type    = 'form';
	$post->post_status  = 'publish';
	$post->post_content = '<!-- wp:prc-block/form {"formName":"Contact","method":"rest","namespace":"prc-block/form","action":"sendToEmail","actionConfig":{"forwardTo":"' . $stored . '"}} /-->';
	$GLOBALS['__posts'][ $form_id ] = $post;

	$resolved = Form_Send_Email::resolve_forward_to(
		array(
			'formPostId'   => $form_id,
			'actionConfig' => array(
				'forwardTo'    => 'attacker@evil.example',
				'forwardToSig' => Form_Send_Email::sign_forward_to( 'attacker@evil.example' ),
			),
		)
	);
	assert_eq( $stored, $resolved, 'CPT path ignores client forwardTo' );

	$missing = Form_Send_Email::resolve_forward_to(
		array(
			'formPostId'   => 999,
			'actionConfig' => array(
				'forwardTo'    => 'attacker@evil.example',
				'forwardToSig' => Form_Send_Email::sign_forward_to( 'attacker@evil.example' ),
			),
		)
	);
	assert_true( is_wp_error( $missing ), 'missing form CPT fails closed' );
	assert_eq( 'missing_forward_to', $missing->get_error_code(), 'missing CPT error code' );

	// --- CPT path: draft/private/future match Form_Renderer visibility ---
	$draft_id = 43;
	$draft    = new \WP_Post();
	$draft->ID           = $draft_id;
	$draft->post_type    = 'form';
	$draft->post_status  = 'draft';
	$draft->post_content = '<!-- wp:prc-block/form {"formName":"Draft Contact","method":"rest","namespace":"prc-block/form","action":"sendToEmail","actionConfig":{"forwardTo":"' . $stored . '"}} /-->';
	$GLOBALS['__posts'][ $draft_id ] = $draft;

	$GLOBALS['__logged_in'] = false;
	$draft_anon             = Form_Send_Email::resolve_forward_to(
		array(
			'formPostId'   => $draft_id,
			'actionConfig' => array(),
		)
	);
	assert_true( is_wp_error( $draft_anon ), 'draft CPT rejected when logged out' );
	assert_eq( 'missing_forward_to', $draft_anon->get_error_code(), 'draft logged-out error code' );

	$GLOBALS['__logged_in'] = true;
	$draft_auth             = Form_Send_Email::resolve_forward_to(
		array(
			'formPostId'   => $draft_id,
			'actionConfig' => array(
				'forwardTo'    => 'attacker@evil.example',
				'forwardToSig' => Form_Send_Email::sign_forward_to( 'attacker@evil.example' ),
			),
		)
	);
	assert_eq( $stored, $draft_auth, 'draft CPT resolves for logged-in user' );
	$GLOBALS['__logged_in'] = false;

	if ( $GLOBALS['__failed'] > 0 ) {
		echo "\n{$GLOBALS['__failed']} failure(s)\n";
		exit( 1 );
	}

	echo "\nAll tests passed.\n";
	exit( 0 );
}
