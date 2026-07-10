<?php
/**
 * Form Block
 *
 * @package PRC\Platform\Block_Forms
 */

namespace PRC\Platform\Block_Forms;

/**
 * Form Response Log.
 *
 * Logs form submissions to the responses table (see Form_Response_Repository)
 * and registers the standalone `logResponse` REST action so editors can build
 * "save only" forms that store responses without sending email.
 *
 * @package PRC\Platform\Block_Forms
 */
class Form_Response_Log {
	/**
	 * Object-cache group for the log-response throttle buckets.
	 */
	const THROTTLE_CACHE_GROUP = 'prc_block_form_log_response_throttle';

	/**
	 * Default per-IP submission limit and window (fixed window).
	 */
	const THROTTLE_IP_LIMIT  = 20;
	const THROTTLE_IP_WINDOW = 10 * MINUTE_IN_SECONDS;

	/**
	 * System field types that are stripped before storage.
	 *
	 * @var string[]
	 */
	const SYSTEM_FIELD_TYPES = array( 'captchaToken', 'nonceToken' );

	/**
	 * Constructor.
	 *
	 * @param \PRC\Platform\Block_Forms\Loader $loader The loader.
	 */
	public function __construct( $loader ) {
		if ( null !== $loader ) {
			$loader->add_action( 'rest_api_init', $this, 'register_rest_endpoints' );
		}
	}

	/**
	 * @hook rest_api_init
	 */
	public function register_rest_endpoints() {
		register_rest_route(
			'prc-api/v3',
			'form/log-response',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_log_response_submission' ),
				'permission_callback' => function () {
					return true;
				},
			)
		);
	}

	/**
	 * Log a form response to the responses table.
	 *
	 * Public so other form action handlers (send-to-email, system emails,
	 * mailchimp, etc.) can opt in to response logging.
	 *
	 * @param array $args {
	 *     Response data.
	 *
	 *     @type int    $form_post_id The form CPT post ID, when the submission came through a synced form. Default 0.
	 *     @type string $form_name    The form name.
	 *     @type string $action       The form action, e.g. 'sendToEmail' or 'logResponse'.
	 *     @type string $status       Response status, e.g. 'received', 'sent', 'send_failed'. Default 'received'.
	 *     @type array  $fields       The submitted form fields (raw; sanitized and stripped of system fields here).
	 * }
	 * @return int|false The response row ID on success, false when logging is disabled or fails.
	 */
	public static function log( array $args ) {
		if ( ! class_exists( '\PRC\Platform\Block_Forms\Form_Response_Repository' ) ) {
			return false;
		}

		/**
		 * Filter whether form response logging is enabled.
		 *
		 * @param bool  $enabled Whether to log this response. Default true.
		 * @param array $args    The response data about to be logged.
		 */
		if ( ! apply_filters( 'prc_block_form_response_logging_enabled', true, $args ) ) {
			return false;
		}

		$fields = self::sanitize_fields( $args['fields'] ?? array() );
		if ( empty( $fields ) ) {
			return false;
		}

		$form_post_id = absint( $args['form_post_id'] ?? 0 );
		if ( $form_post_id ) {
			$form_post = get_post( $form_post_id );
			if ( ! $form_post || ( class_exists( '\PRC\Platform\Block_Forms\Forms' ) && Forms::POST_TYPE !== $form_post->post_type ) ) {
				$form_post_id = 0;
			}
		}

		$source_url     = wp_get_referer() ? esc_url_raw( wp_get_referer() ) : '';
		$source_post_id = 0;
		if ( $source_url && function_exists( 'wpcom_vip_url_to_postid' ) ) {
			$source_post_id = wpcom_vip_url_to_postid( $source_url );
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '';

		$repository = new Form_Response_Repository();
		$row_id     = $repository->insert(
			array(
				'form_id'        => $form_post_id,
				'form_name'      => sanitize_text_field( $args['form_name'] ?? '' ),
				'action'         => sanitize_text_field( $args['action'] ?? '' ),
				'status'         => sanitize_text_field( $args['status'] ?? 'received' ),
				'email'          => self::find_email_in_fields( $fields ),
				'from_name'      => self::find_name_in_fields( $fields ),
				'fields'         => $fields,
				'source_url'     => substr( $source_url, 0, 255 ),
				'source_post_id' => $source_post_id,
				'user_id'        => get_current_user_id(),
				'user_agent'     => $user_agent,
				'is_spam'        => self::classify_spam( $fields, $args ),
			)
		);

		if ( false === $row_id ) {
			return false;
		}

		/**
		 * Fires after a form response is logged.
		 *
		 * @param int   $row_id The response row ID.
		 * @param array $args   The response data.
		 */
		do_action( 'prc_platform_form_response_logged', $row_id, $args );

		return $row_id;
	}

	/**
	 * Classify a response as spam at log time.
	 *
	 * Spam is foldered, never dropped — the response is stored with
	 * `is_spam = 1` and lands in the Responses screen's Spam folder, so
	 * false positives stay recoverable via "Not spam".
	 *
	 * Built-in heuristics: link flooding (3+ URLs across field values,
	 * threshold filterable) and an editor-managed term blocklist (empty by
	 * default). When the Akismet plugin is active and configured, its
	 * comment-check verdict is consulted as well.
	 *
	 * @param array $fields The sanitized fields.
	 * @param array $args   The response data passed to log().
	 * @return bool Whether the response is spam.
	 */
	public static function classify_spam( array $fields, array $args ) {
		$is_spam = false;

		$values = implode( "\n", array_column( $fields, 'value' ) );

		/**
		 * Filter the number of URLs across field values that marks a
		 * response as spam. Return 0 to disable the link-flood heuristic.
		 *
		 * @param int $threshold URL count threshold. Default 3.
		 */
		$link_threshold = (int) apply_filters( 'prc_form_response_spam_link_threshold', 3 );
		if ( $link_threshold > 0 && preg_match_all( '#https?://#i', $values ) >= $link_threshold ) {
			$is_spam = true;
		}

		/**
		 * Filter the blocklisted terms that mark a response as spam.
		 * Matching is case-insensitive against all field values. Empty by
		 * default so editors can react to spam campaigns without a deploy.
		 *
		 * @param string[] $terms Blocklisted terms. Default empty.
		 */
		$blocklist = apply_filters( 'prc_form_response_spam_terms', array() );
		if ( ! $is_spam && is_array( $blocklist ) && ! empty( $blocklist ) ) {
			$haystack = strtolower( $values );
			foreach ( $blocklist as $term ) {
				if ( '' !== (string) $term && false !== strpos( $haystack, strtolower( (string) $term ) ) ) {
					$is_spam = true;
					break;
				}
			}
		}

		if ( ! $is_spam ) {
			$is_spam = self::check_akismet( $fields );
		}

		/**
		 * Filter whether a form response is spam.
		 *
		 * @param bool  $is_spam Whether the response was classified as spam.
		 * @param array $fields  The sanitized fields.
		 * @param array $args    The response data passed to log().
		 */
		return (bool) apply_filters( 'prc_form_response_is_spam', $is_spam, $fields, $args );
	}

	/**
	 * Consult Akismet's comment-check for a spam verdict, when available.
	 *
	 * Silently skipped when the Akismet plugin is inactive or has no API
	 * key. Failures are treated as "not spam" — Akismet is advisory here.
	 *
	 * @param array $fields The sanitized fields.
	 * @return bool Whether Akismet judged the submission spam.
	 */
	private static function check_akismet( array $fields ) {
		if ( ! class_exists( '\Akismet' ) || ! method_exists( '\Akismet', 'get_api_key' ) || ! method_exists( '\Akismet', 'http_post' ) ) {
			return false;
		}
		if ( ! \Akismet::get_api_key() ) {
			return false;
		}

		$content = array();
		foreach ( $fields as $field ) {
			if ( '' !== (string) $field['value'] ) {
				$content[] = $field['value'];
			}
		}

		$body = array(
			'blog'                 => get_option( 'home' ),
			'user_ip'              => function_exists( '\\PRC\\Platform\\get_client_ip' ) ? \PRC\Platform\get_client_ip() : '',
			'user_agent'           => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'referrer'             => wp_get_referer() ? esc_url_raw( wp_get_referer() ) : '',
			'comment_type'         => 'contact-form',
			'comment_author'       => self::find_name_in_fields( $fields ),
			'comment_author_email' => self::find_email_in_fields( $fields ),
			'comment_content'      => implode( "\n", $content ),
		);

		$response = \Akismet::http_post( http_build_query( $body ), 'comment-check' );

		return isset( $response[1] ) && 'true' === trim( (string) $response[1] );
	}

	/**
	 * Sanitize submitted fields and strip system fields (captcha/nonce tokens).
	 *
	 * @param array $fields The raw submitted fields.
	 * @return array The sanitized fields.
	 */
	public static function sanitize_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || ! isset( $field['type'] ) ) {
				continue;
			}
			$type = sanitize_text_field( $field['type'] );
			if ( in_array( $type, self::SYSTEM_FIELD_TYPES, true ) ) {
				continue;
			}
			$value = $field['value'] ?? '';
			if ( 'textarea' === $type ) {
				$value = sanitize_textarea_field( $value );
			} else {
				$value = sanitize_text_field( $value );
			}
			$sanitized[] = array(
				'id'      => sanitize_text_field( $field['id'] ?? '' ),
				'name'    => sanitize_text_field( $field['name'] ?? '' ),
				'label'   => sanitize_text_field( $field['label'] ?? '' ),
				'type'    => $type,
				'value'   => $value,
				'checked' => isset( $field['checked'] ) ? (bool) $field['checked'] : null,
			);
		}

		return $sanitized;
	}

	/**
	 * Find the first email address in the sanitized fields.
	 *
	 * @param array $fields The sanitized fields.
	 * @return string The email address, or empty string.
	 */
	public static function find_email_in_fields( array $fields ) {
		foreach ( $fields as $field ) {
			if ( 'email' === $field['type'] && ! empty( $field['value'] ) && filter_var( $field['value'], FILTER_VALIDATE_EMAIL ) ) {
				return $field['value'];
			}
		}
		return '';
	}

	/**
	 * Find the submitter's name in the sanitized fields — the first non-email
	 * field whose name or label contains "name".
	 *
	 * @param array $fields The sanitized fields.
	 * @return string The name, or empty string.
	 */
	public static function find_name_in_fields( array $fields ) {
		foreach ( $fields as $field ) {
			if ( 'email' === $field['type'] || empty( $field['value'] ) ) {
				continue;
			}
			$haystack = strtolower( $field['name'] . ' ' . $field['label'] );
			if ( false !== strpos( $haystack, 'name' ) ) {
				return $field['value'];
			}
		}
		return '';
	}

	/**
	 * Handle the standalone `logResponse` submission.
	 *
	 * Follows the same public-form envelope as the sendToEmail action: captcha
	 * verification and per-IP rate limiting are the gates (page-baked nonces
	 * expire on edge-cached pages, so they are intentionally not verified).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_log_response_submission( $request ) {
		$form_data = json_decode( $request->get_body(), true );

		if ( ! $form_data || ! is_array( $form_data ) ) {
			return new \WP_Error( 'invalid_form_data', 'Invalid form data provided.', array( 'status' => 400 ) );
		}

		$form_name   = sanitize_text_field( $form_data['formName'] ?? '' );
		$form_fields = $form_data['formFields'] ?? array();
		if ( ! is_array( $form_fields ) || empty( $form_fields ) ) {
			return new \WP_Error( 'empty_form_fields', 'No form fields provided.', array( 'status' => 400 ) );
		}

		$throttled = $this->enforce_throttle();
		if ( is_wp_error( $throttled ) ) {
			return $throttled;
		}

		$captcha_token = function_exists( '\\PRC\\Platform\\find_captcha_token_in_form_fields' )
			? \PRC\Platform\find_captcha_token_in_form_fields( $form_fields )
			: '';
		if ( function_exists( '\\PRC\\Platform\\verify_captcha' ) ) {
			$remote_ip = function_exists( '\\PRC\\Platform\\get_client_ip' )
				? \PRC\Platform\get_client_ip()
				: '';

			if ( ! \PRC\Platform\verify_captcha( $captcha_token, '' !== $remote_ip ? $remote_ip : null ) ) {
				return new \WP_Error( 'captcha_failed', 'Captcha verification failed.', array( 'status' => 403 ) );
			}
		}

		$row_id = self::log(
			array(
				'form_post_id' => absint( $form_data['formPostId'] ?? 0 ),
				'form_name'    => $form_name,
				'action'       => 'logResponse',
				'status'       => 'received',
				'fields'       => $form_fields,
			)
		);

		if ( false === $row_id ) {
			return new \WP_Error( 'form_submission_failed', 'Form submission could not be saved.', array( 'status' => 500 ) );
		}

		return new \WP_REST_Response(
			array(
				'status'     => 'success',
				'message'    => wp_sprintf(
					'%s submitted successfully!',
					$form_name,
				),
				'formFields' => self::sanitize_fields( $form_fields ),
			),
			200
		);
	}

	/**
	 * Enforce per-IP submission throttling.
	 *
	 * @return true|\WP_Error
	 */
	private function enforce_throttle() {
		if ( ! function_exists( '\\PRC\\Platform\\rate_limit_hit' ) ) {
			return true;
		}

		/**
		 * Filter the throttle limits for the logResponse form action.
		 *
		 * @param array{limit:int,window:int} $limits
		 */
		$limits = apply_filters(
			'prc_block_form_log_response_throttle',
			array(
				'limit'  => self::THROTTLE_IP_LIMIT,
				'window' => self::THROTTLE_IP_WINDOW,
			)
		);

		$ip = function_exists( '\\PRC\\Platform\\get_client_ip' )
			? \PRC\Platform\get_client_ip()
			: '';

		if ( '' !== $ip && isset( $limits['limit'], $limits['window'] ) ) {
			if ( \PRC\Platform\rate_limit_hit(
				'ip_' . md5( $ip ),
				(int) $limits['limit'],
				(int) $limits['window'],
				self::THROTTLE_CACHE_GROUP
			) ) {
				return new \WP_Error( 'rate_limited', 'Too many requests. Please try again later.', array( 'status' => 429 ) );
			}
		}

		return true;
	}
}
