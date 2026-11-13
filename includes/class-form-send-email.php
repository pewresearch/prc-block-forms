<?php
/**
 * Form Block
 *
 * @package PRC\Platform\Block_Forms
 */

namespace PRC\Platform\Block_Forms;

/**
 * Form Send Email.
 *
 * @package PRC\Platform\Block_Forms
 */
class Form_Send_Email {

	/**
	 * Object-cache group for the send-to-email throttle buckets.
	 */
	const THROTTLE_CACHE_GROUP = 'prc_block_form_send_email_throttle';

	/**
	 * Default per-IP send limit and window (fixed window).
	 */
	const THROTTLE_IP_LIMIT  = 10;
	const THROTTLE_IP_WINDOW = 10 * MINUTE_IN_SECONDS;

	/**
	 * Default per-recipient send limit and window (fixed window).
	 */
	const THROTTLE_EMAIL_LIMIT  = 5;
	const THROTTLE_EMAIL_WINDOW = HOUR_IN_SECONDS;

	/**
	 * Lease TTL, in seconds, for one submission ID. A request that dies mid-run holds its ID this long,
	 * and the page shows "still sending" until it ends.
	 *
	 * Measured on the dev-env, a run takes 0.2 seconds with a 150 ms mail send and 5.1 seconds with a
	 * 5 second send. The worst case is the spam check at its 5 second budget, then the two Mandrill
	 * requests of the send at their caps: the ping at MANDRILL_PING_TIME_BUDGET and the message call at
	 * MAIL_TIME_BUDGET. That is 23 seconds. The TTL must stay above it with 5 seconds of margin, or a
	 * retry could send a second email. A unit test measures the Mandrill waits and checks it.
	 *
	 * The captcha check is not in the bound. It runs before the claim, so a slow Turnstile never holds
	 * the lease. Before the insert and before the send, the handler checks the lease. The check extends
	 * the lease, so the send always starts with a full TTL, and it stops a run that lost its lease.
	 */
	const SUBMISSION_LEASE_TTL = 30;

	/**
	 * Seconds the Turnstile check may wait. verify_captcha() has no timeout of its own, and seven plugins
	 * share it, so the form passes this limit with each call. A call that runs out fails the captcha, as
	 * a rejected token does, and the page shows the error it already shows for a failed captcha.
	 */
	const CAPTCHA_TIME_BUDGET = 5.0;

	/**
	 * Seconds the message call of the form's notification may wait on the Mandrill API. The Mandrill plugin
	 * allows a send 120 seconds. The cap is scoped to this send, so bulk Mandrill sends keep their 120 seconds.
	 */
	const MAIL_TIME_BUDGET = 15.0;

	/**
	 * Seconds any other Mandrill request of the form's notification may wait. In the plugin that is only
	 * users/ping2: the first send of a PHP request connects, and the connection pings with a 15 second
	 * timeout before the message call. A ping that fails ends before any message call, so Mandrill never
	 * saw the message and the native fallback is safe.
	 */
	const MANDRILL_PING_TIME_BUDGET = 3.0;

	/**
	 * The three ways the form's notification can end. The mailer accepted it, the mailer refused it
	 * for certain, or nobody can tell whether Mandrill accepted it (a timeout, a lost answer).
	 */
	const SEND_SENT    = 'sent';
	const SEND_FAILED  = 'send_failed';
	const SEND_UNKNOWN = 'send_unknown';

	/**
	 * The cURL errors that end before Mandrill could read the request: no DNS answer, no connection, or a
	 * TLS setup that failed. Mandrill never saw the message, so the native mailer may send it.
	 */
	const CURL_NOT_SENT = array( 1, 3, 5, 6, 7, 35, 51, 58, 59, 60, 64, 66, 77 );

	/**
	 * Define validation rules for each field type.
	 *
	 * @var array
	 */
	private const FIELD_VALIDATION_RULES = array(
		'email'          => array(
			'required'  => true,
			'validator' => 'validate_email',
		),
		'text'           => array(
			'required'  => false,
			'validator' => 'validate_text',
		),
		'textarea'       => array(
			'required'  => false,
			'validator' => 'validate_text',
		),
		'number'         => array(
			'required'  => false,
			'validator' => 'validate_number',
		),
		'tel'            => array(
			'required'  => false,
			'validator' => 'validate_tel',
		),
		'url'            => array(
			'required'  => false,
			'validator' => 'validate_url',
		),
		'date'           => array(
			'required'  => false,
			'validator' => 'validate_date',
		),
		'datetime-local' => array(
			'required'  => false,
			'validator' => 'validate_datetime',
		),
		'time'           => array(
			'required'  => false,
			'validator' => 'validate_time',
		),
		'week'           => array(
			'required'  => false,
			'validator' => 'validate_week',
		),
		'month'          => array(
			'required'  => false,
			'validator' => 'validate_month',
		),
		'search'         => array(
			'required'  => false,
			'validator' => 'validate_text',
		),
		'select'         => array(
			'required'  => false,
			'validator' => 'validate_text',
		),
		'checkbox'       => array(
			'required'  => false,
			'validator' => 'validate_checkbox',
		),
		'radio'          => array(
			'required'  => false,
			'validator' => 'validate_radio',
		),
		'hidden'         => array(
			'required'  => false,
			'validator' => 'validate_text',
		),
		'captchaToken'   => array(
			'required'  => false,
			'validator' => 'validate_captcha',
		),
		'nonceToken'     => array(
			'required'  => false,
			'validator' => 'validate_nonce',
		),
	);

	/**
	 * Constructor.
	 *
	 * @param \PRC\Platform\Block_Forms\Loader $loader The loader.
	 */
	public function __construct( $loader ) {
		$this->init( $loader );
	}

	/**
	 * Initialize the class.
	 *
	 * @param \PRC\Platform\Block_Forms\Loader $loader The loader.
	 */
	public function init( $loader = null ) {
		if ( null !== $loader ) {
			$loader->add_action( 'rest_api_init', $this, 'register_rest_endpoints' );
		}
	}

	/**
	 * Registers the send-to-email REST route.
	 *
	 * @hook rest_api_init
	 */
	public function register_rest_endpoints() {
		register_rest_route(
			'prc-api/v3',
			'form/send-to-email',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_email_submission' ),
				'permission_callback' => function () {
					return true;
				},
			)
		);
	}

	/**
	 * Validate a single form field.
	 *
	 * @param array $field The field data.
	 * @return \WP_Error|true Returns true if valid, WP_Error if invalid.
	 */
	private function validate_field( $field ) {
		// Check if this field is required.
		$is_required = isset( $field['required'] ) ? (bool) $field['required'] : false;

		// Ensure required field properties exist.
		if ( ! isset( $field['type'] ) ) {
			return new \WP_Error(
				'invalid_field_structure',
				'Field missing required type property.',
				array(
					'status' => 400,
					'field'  => $field,
				)
			);
		}

		$field_type  = sanitize_text_field( $field['type'] );
		$field_value = sanitize_text_field( $field['value'] );

		// Check if field type is supported.
		if ( ! isset( self::FIELD_VALIDATION_RULES[ $field_type ] ) ) {
			return new \WP_Error(
				'unsupported_field_type',
				"Unsupported field type: {$field_type}",
				array(
					'status' => 400,
					'field'  => $field,
				)
			);
		}

		$rules = self::FIELD_VALIDATION_RULES[ $field_type ];

		// Check if required field is empty.
		if ( $is_required && empty( $field_value ) ) {
			return new \WP_Error(
				'required_field_empty',
				"Required field of type '{$field_type}' cannot be empty.",
				array(
					'status' => 400,
					'field'  => $field,
				)
			);
		}

		// Skip validation if field is not required and empty.
		if ( ! $is_required && empty( $field_value ) ) {
			return true;
		}

		// Run type-specific validation.
		$validator = $rules['validator'];
		if ( method_exists( $this, $validator ) ) {
			return $this->$validator( $field_value, $field_type );
		}

		return true;
	}

	/**
	 * Validate email field.
	 *
	 * @param string $value The field value.
	 * @param string $type The field type.
	 * @return \WP_Error|true
	 */
	private function validate_email( $value, $type = 'email' ) {
		if ( ! filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
			return new \WP_Error( 'invalid_email', 'Invalid email address format.', array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Validate text fields (text, textarea, search).
	 *
	 * @param string $value The field value.
	 * @param string $type The field type.
	 * @return \WP_Error|true
	 */
	private function validate_text( $value, $type = 'text' ) {
		// Basic sanitization check.
		if ( sanitize_text_field( $value ) !== $value && 'textarea' !== $type ) {
			return new \WP_Error( 'invalid_text', 'Text field contains invalid characters.', array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Validate number field.
	 *
	 * @param string $value The field value.
	 * @param string $type The field type.
	 * @return \WP_Error|true
	 */
	private function validate_number( $value, $type = 'number' ) {
		if ( ! is_numeric( $value ) ) {
			return new \WP_Error( 'invalid_number', 'Number field must contain a valid number.', array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Validate telephone field.
	 *
	 * @param string $value The field value.
	 * @param string $type The field type.
	 * @return \WP_Error|true
	 */
	private function validate_tel( $value, $type = 'tel' ) {
		// Basic phone number validation - allows numbers, spaces, dashes, parentheses, plus.
		if ( ! preg_match( '/^[\d\s\-\(\)\+\.]+$/', $value ) ) {
			return new \WP_Error( 'invalid_tel', 'Telephone field contains invalid characters.', array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Validate URL field.
	 *
	 * @param string $value The field value.
	 * @param string $type The field type.
	 * @return \WP_Error|true
	 */
	private function validate_url( $value, $type = 'url' ) {
		if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return new \WP_Error( 'invalid_url', 'URL field must contain a valid URL.', array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Validate date field.
	 *
	 * @param string $value The field value.
	 * @param string $type The field type.
	 * @return \WP_Error|true
	 */
	private function validate_date( $value, $type = 'date' ) {
		$date = \DateTime::createFromFormat( 'Y-m-d', $value );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
			return new \WP_Error( 'invalid_date', 'Date field must be in YYYY-MM-DD format.', array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Validate datetime-local field.
	 *
	 * @param string $value The field value.
	 * @param string $type The field type.
	 * @return \WP_Error|true
	 */
	private function validate_datetime( $value, $type = 'datetime-local' ) {
		$datetime = \DateTime::createFromFormat( 'Y-m-d\TH:i', $value );
		if ( ! $datetime || $datetime->format( 'Y-m-d\TH:i' ) !== $value ) {
			return new \WP_Error( 'invalid_datetime', 'Datetime field must be in YYYY-MM-DDTHH:MM format.', array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Validate time field.
	 *
	 * @param string $value The field value.
	 * @param string $type The field type.
	 * @return \WP_Error|true
	 */
	private function validate_time( $value, $type = 'time' ) {
		$time = \DateTime::createFromFormat( 'H:i', $value );
		if ( ! $time || $time->format( 'H:i' ) !== $value ) {
			return new \WP_Error( 'invalid_time', 'Time field must be in HH:MM format.', array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Validate week field.
	 *
	 * @param string $value The field value.
	 * @param string $type The field type.
	 * @return \WP_Error|true
	 */
	private function validate_week( $value, $type = 'week' ) {
		if ( ! preg_match( '/^\d{4}-W\d{2}$/', $value ) ) {
			return new \WP_Error( 'invalid_week', 'Week field must be in YYYY-WNN format.', array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Validate month field.
	 *
	 * @param string $value The field value.
	 * @param string $type The field type.
	 * @return \WP_Error|true
	 */
	private function validate_month( $value, $type = 'month' ) {
		$date = \DateTime::createFromFormat( 'Y-m', $value );
		if ( ! $date || $date->format( 'Y-m' ) !== $value ) {
			return new \WP_Error( 'invalid_month', 'Month field must be in YYYY-MM format.', array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Validate captcha field.
	 *
	 * @param string $value The field value.
	 * @param string $type The field type.
	 * @return \WP_Error|true
	 */
	private function validate_captcha( $value, $type = 'captchaToken' ) {
		// Captcha is verified once, authoritatively, in handle_email_submission().
		// Turnstile tokens are single-use, so re-verifying the same token here
		// (this method runs inside the per-field validation loop) would always
		// fail with "timeout-or-duplicate". Intentionally a no-op.
		return true;
	}

	/**
	 * Validate nonce field.
	 *
	 * @param string $value The field value.
	 * @param string $type The field type.
	 * @return \WP_Error|true
	 */
	private function validate_nonce( $value, $type = 'nonceToken', ) {
		// Nonce is not verified here — page-embedded prc-block-form nonces expire
		// on edge-cached pages. Captcha + send throttling in handle_email_submission()
		// are the real gates. Cached pages may still submit a stale nonceToken field.
		return true;
	}

	/**
	 * Format the email body with HTML table styling.
	 * Safely sanitizes the values and labels before outputting them.
	 *
	 * @param array  $form_fields The form fields data.
	 * @param string $form_name The name of the form.
	 * @return string The formatted HTML message.
	 */
	private static function format_body( $form_fields, $form_name ) {
		$message = '';
		foreach ( $form_fields as $index => $field ) {
			// Skip system fields like captcha and nonce tokens.
			if ( in_array( $field['type'], array( 'captchaToken', 'nonceToken' ), true ) ) {
				continue;
			}

			$label = isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : 'Unlabeled Field';
			// If the label is an empty string, use the field name.
			if ( empty( $label ) ) {
				$label = sanitize_text_field( $field['name'] );
			}

			$value = sanitize_text_field( $field['value'] );

			// Handle checkbox values.
			if ( 'checkbox' === $field['type'] ) {
				$value = isset( $field['checked'] ) && $field['checked'] ? 'Yes' : 'No';
			}

			if ( 'radio' === $field['type'] ) {
				// If not checked, skip.
				if ( ! isset( $field['checked'] ) ||
					! $field['checked'] ||
					! isset( $field['value'] ) ||
					! $field['value'] ) {
					continue;
				}
				$label = sanitize_text_field( $field['name'] );
				$value = sanitize_text_field( $field['value'] );
			}

			// Handle empty values.
			if ( empty( $value ) && 'checkbox' !== $field['type'] ) {
				$value = '<em style="color: #95a5a6;">Not provided</em>';
			}

			// Alternate row colors.
			$row_color = ( 0 === $index % 2 ) ? '#f8f9fa' : '#ffffff';

			$message .= '
						<tr style="background-color: ' . $row_color . ';">
							<td style="padding: 12px 15px; border-bottom: 1px solid #ecf0f1; font-weight: 600; color: #2c3e50;">' . esc_html( $label ) . '</td>
							<td style="padding: 12px 15px; border-bottom: 1px solid #ecf0f1;">' . $value . '</td>
						</tr>';
		}

		return $message;
	}

	/**
	 * Format the email message with HTML table styling.
	 *
	 * @param array  $form_fields The form fields data.
	 * @param string $form_name The name of the form.
	 * @param string $submitted_gmt When the reader submitted, as a UTC `Y-m-d H:i:s` string. Empty means now.
	 * @return string The formatted HTML message.
	 */
	private static function format_message( $form_fields, $form_name, string $submitted_gmt = '' ) {
		$form_name = sanitize_text_field( $form_name );
		$format    = 'F j, Y, g:i a';
		$time      = '' !== $submitted_gmt && false !== strtotime( $submitted_gmt ) && ! str_starts_with( $submitted_gmt, '0000' )
			? get_date_from_gmt( $submitted_gmt, $format )
			: current_time( $format );

		$message = '
		<html>
		<head>
			<title>Form Submission from ' . esc_html( $form_name ) . '</title>
		</head>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<h2 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">New Form Submission</h2>
				<p style="margin-bottom: 20px;"><strong>Form:</strong> ' . esc_html( $form_name ) . '</p>
				<table style="width: 100%; border-collapse: collapse; margin: 20px 0; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
					<thead>
						<tr style="background-color: #3498db; color: white;">
							<th style="padding: 12px 15px; text-align: left; border-bottom: 2px solid #2980b9;">Field</th>
							<th style="padding: 12px 15px; text-align: left; border-bottom: 2px solid #2980b9;">Value</th>
						</tr>
					</thead>
					<tbody>';

		$message .= self::format_body( $form_fields, $form_name );

		$message .= '
					</tbody>
				</table>
				<div style="margin-top: 30px; padding: 15px; background-color: #ecf0f1; border-left: 4px solid #3498db; color: #2c3e50;">
					<p style="margin: 0; font-size: 14px;"><strong>Submission Time:</strong> ' . esc_html( $time ) . '</p>
					<p style="margin: 0; font-size: 13px;"><strong>Powered by:</strong> PRC Platform Forms</p>
				</div>
			</div>
		</body>
		</html>';

		return $message;
	}

	/**
	 * Handle the email submission.
	 *
	 * This is our first standardized "rest" action for prc-block/form.
	 * It should follow a common pattern.
	 * All responses will include the formName, formId, and formFields.
	 * The formFields will be an array of objects with the following properties:
	 * - id: string
	 * - name: string
	 * - type: string
	 * - value: string
	 * - required: boolean
	 * - checked: boolean | null
	 *
	 * A request carries a client submission ID. One ID makes one submission:
	 * a repeat of an ID that already succeeded returns the stored answer and sends
	 * nothing, and a repeat while the first request still runs gets a 409. Only a
	 * success is stored, and only its status and message. A failure leaves the ID
	 * free, so the retry runs again. A request with no ID comes from a page that was
	 * open before the ID shipped. It is handled with no claim and logged. An ID that
	 * is not a UUID gets a 400.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_email_submission( $request ) {
		$form_data = json_decode( $request->get_body(), true );

		if ( ! $form_data || ! is_array( $form_data ) ) {
			return new \WP_Error( 'invalid_form_data', 'Invalid form data provided.', array( 'status' => 400 ) );
		}

		$submission_id = self::read_submission_id( $form_data );
		if ( is_wp_error( $submission_id ) ) {
			return $submission_id;
		}

		$form_id   = sanitize_text_field( $form_data['formId'] ?? '' );
		$form_name = sanitize_text_field( $form_data['formName'] ?? '' );

		$target = self::resolve_forward_to( $form_data );
		if ( is_wp_error( $target ) ) {
			return $target;
		}

		$form_fields = $form_data['formFields'] ?? array();
		if ( ! is_array( $form_fields ) || empty( $form_fields ) ) {
			return new \WP_Error( 'empty_form_fields', 'No form fields provided.', array( 'status' => 400 ) );
		}

		// An ID that another request holds answers 409 before the captcha. A token is single use, and the page posts the same token again after a 409, so the 409 must not spend it.
		if ( '' !== $submission_id && self::submission_is_held( $form_id, $submission_id ) ) {
			return self::in_progress_error();
		}

		// The captcha comes before the claim and has a time limit. A replay needs a valid token too: the page mounts a new one for each press. A bad token, or a Turnstile that times out, never takes the claim.
		$captcha = $this->check_captcha( $form_fields );
		if ( is_wp_error( $captcha ) ) {
			return $captcha;
		}

		$process = fn( ?\Closure $holds_lease = null ) => $this->process_email_submission( $form_data, $target, $form_fields, $form_name, $form_id, (string) $submission_id, $holds_lease );

		if ( '' === $submission_id ) {
			error_log( sprintf( '[prc-block-forms] Form "%s" got a submission with no submission ID. A page that was open before the ID shipped sent it. Handling it with no claim.', $form_name ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- VIP routes this to the log stream.
			return $process();
		}

		if ( ! function_exists( '\\PRC\\Primitives\\Concurrency\\run_once' ) || ! class_exists( '\\PRC\\Primitives\\Concurrency\\Table_Claim_Store' ) ) {
			return $process();
		}

		$answer = null;
		$result = \PRC\Primitives\Concurrency\run_once(
			'form_' . $form_id . '_' . $submission_id,
			function ( $holds_lease = null ) use ( &$answer, $process ) {
				$answer = $process( $holds_lease instanceof \Closure ? $holds_lease : null );
				if ( ! ( $answer instanceof \WP_REST_Response && 200 === $answer->get_status() ) ) {
					return \PRC\Primitives\Concurrency\Run_Result::retryable( is_wp_error( $answer ) ? $answer->get_error_code() : '' );
				}
				$data = $answer->get_data();
				// The claims table sits outside the retention and erasure paths of the form responses, so it keeps only what the page renders. Never the submitted fields.
				$json = wp_json_encode(
					array(
						'status'  => $data['status'] ?? 'success',
						'message' => $data['message'] ?? '',
					)
				);
				return \PRC\Primitives\Concurrency\Run_Result::final( false === $json ? '{"status":"success","message":""}' : $json );
			},
			new \PRC\Primitives\Concurrency\Table_Claim_Store(),
			self::SUBMISSION_LEASE_TTL
		);

		// This request ran the handler. Its own answer is the answer, whatever happened to the claim after.
		if ( null !== $answer ) {
			return $answer;
		}

		if ( \PRC\Primitives\Concurrency\Run_Result::BUSY === $result->status ) {
			return self::in_progress_error();
		}

		$stored = \PRC\Primitives\Concurrency\Run_Result::FINAL === $result->status ? json_decode( $result->value, true ) : null;
		if ( is_array( $stored ) ) {
			$replay = new \WP_REST_Response(
				array(
					'status'  => (string) ( $stored['status'] ?? 'success' ),
					'message' => (string) ( $stored['message'] ?? '' ),
				),
				200
			);
			$replay->header( 'X-PRC-Form-Replay', '1' );
			return $replay;
		}

		// The claim store cannot be read. Failing open can send twice. Failing closed can lose the entry. A lost entry is worse.
		error_log( sprintf( '[prc-block-forms] The submission claim store failed for "%s". Handling the submission without a claim.', $form_name ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- VIP routes this to the log stream.
		return $process();
	}

	/**
	 * Read the client submission ID from the request.
	 *
	 * @param array $form_data Decoded request body.
	 * @return string|\WP_Error The lowercase UUID, an empty string when the request has none, or a 400 error when it is not a UUID.
	 */
	private static function read_submission_id( array $form_data ) {
		$raw = $form_data['submissionId'] ?? '';
		if ( '' === $raw || null === $raw ) {
			return '';
		}
		if ( ! is_string( $raw ) || ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $raw ) ) {
			return new \WP_Error( 'invalid_submission_id', 'The submission ID is not valid.', array( 'status' => 400 ) );
		}
		return strtolower( $raw );
	}

	/**
	 * The 409 for a submission that another request holds. The page keeps its draft and posts again with the same ID.
	 *
	 * @return \WP_Error
	 */
	private static function in_progress_error(): \WP_Error {
		return new \WP_Error(
			'submission_in_progress',
			'This form is already being sent. Try again in a moment.',
			array(
				'status'       => 409,
				'leaseSeconds' => self::SUBMISSION_LEASE_TTL,
			)
		);
	}

	/**
	 * Whether a request holds the lease of this submission ID right now. A read only: it takes nothing.
	 *
	 * It lets the handler answer 409 before it spends the Turnstile token. run_once() still decides, under the
	 * lease. A request that passes this check and then loses the race for the lease gets the 409 from run_once().
	 *
	 * @param string $form_id       Sanitized form ID.
	 * @param string $submission_id Submission ID, lowercase.
	 * @return bool
	 */
	private static function submission_is_held( string $form_id, string $submission_id ): bool {
		if ( ! class_exists( '\\PRC\\Primitives\\Concurrency\\Run_Once' ) || ! class_exists( '\\PRC\\Primitives\\Concurrency\\Lease' ) ) {
			return false;
		}
		try {
			return ( new \PRC\Primitives\Concurrency\Lease( \PRC\Primitives\Concurrency\Run_Once::LEASE_PREFIX, self::SUBMISSION_LEASE_TTL ) )
				->is_held( \PRC\Primitives\Concurrency\Run_Once::lease_key( 'form_' . $form_id . '_' . $submission_id ) );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Verify the Turnstile token of one POST, once, with a time limit.
	 *
	 * A token is single use, so this is the only place that spends it. A missing helper passes, as in the other consumers.
	 * A Turnstile that does not answer within CAPTCHA_TIME_BUDGET fails the check, with the error of a rejected token.
	 *
	 * @param array $form_fields Submitted fields.
	 * @return true|\WP_Error True when the token is valid, or a 403 captcha_failed error.
	 */
	private function check_captcha( array $form_fields ) {
		if ( ! function_exists( '\\PRC\\Platform\\verify_captcha' ) ) {
			return true;
		}

		$captcha_token = function_exists( '\\PRC\\Platform\\find_captcha_token_in_form_fields' )
			? \PRC\Platform\find_captcha_token_in_form_fields( $form_fields )
			: '';
		$remote_ip     = function_exists( '\\PRC\\Platform\\get_client_ip' )
			? \PRC\Platform\get_client_ip()
			: '';

		if ( ! \PRC\Platform\verify_captcha( $captcha_token, '' !== $remote_ip ? $remote_ip : null, self::CAPTCHA_TIME_BUDGET ) ) {
			return new \WP_Error( 'captcha_failed', 'Captcha verification failed.', array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Validate, store, and send one submission. The captcha is already verified.
	 *
	 * @param array         $form_data    Decoded request body.
	 * @param string        $target       Resolved recipient.
	 * @param array         $form_fields  Submitted fields.
	 * @param string        $form_name    Sanitized form name.
	 * @param string        $form_id      Sanitized form ID.
	 * @param string        $submission_id The client submission ID, or an empty string.
	 * @param \Closure|null $holds_lease  From run_once(): true while this run still holds the lease, and it extends the lease. Null when the run has no claim.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function process_email_submission( array $form_data, $target, array $form_fields, string $form_name, string $form_id = '', string $submission_id = '', ?\Closure $holds_lease = null ) {
		// Find the first field with type 'email' and use its value as the email address.
		$email_address = false;
		foreach ( $form_fields as $field ) {
			if ( isset( $field['type'] ) && 'email' === $field['type'] && ! empty( $field['value'] ) ) {
				$email_address = $field['value'];
				break;
			}
		}

		// Verify the email address is a valid email address.
		if ( ! filter_var( $email_address, FILTER_VALIDATE_EMAIL ) ) {
			return new \WP_Error( 'invalid_email', 'Invalid email address in form submission. Must be a valid email address.', array( 'status' => 400 ) );
		}

		// Validate all form fields efficiently.
		$validated_fields = array();
		foreach ( $form_fields as $field ) {
			$validation_result = $this->validate_field( $field );
			if ( is_wp_error( $validation_result ) ) {
				return $validation_result;
			} else {
				$validated_fields[] = $field;
			}
		}

		$throttled = $this->enforce_send_throttle( $target, 'ip' );
		if ( is_wp_error( $throttled ) ) {
			return $throttled;
		}

		$log_args = array(
			'form_post_id' => absint( $form_data['formPostId'] ?? 0 ),
			'form_name'    => $form_name,
			'action'       => 'sendToEmail',
			'fields'       => $validated_fields,
		);

		// Classify before the row is stored, so the row holds its final folder and a 429 below leaves no row.
		// Spam is classified only when its row can be stored. The verdict fails open: a classifier error or timeout means "not spam".
		$logging             = Form_Response_Log::is_enabled( $log_args );
		$log_args['is_spam'] = $logging && Form_Response_Log::classify_spam_safely( Form_Response_Log::sanitize_fields( $validated_fields ), $log_args );
		$log_args['status']  = $log_args['is_spam'] ? 'spam' : 'pending';

		// Stored spam is not emailed, so spam does not count against the recipient's send limit here.
		if ( ! $log_args['is_spam'] ) {
			$throttled = $this->enforce_send_throttle( $target, 'email' );
			if ( is_wp_error( $throttled ) ) {
				return $throttled;
			}
		}

		$lease_ids = array(
			'form_id'       => $form_id,
			'form_post_id'  => absint( $form_data['formPostId'] ?? 0 ),
			'submission_id' => $submission_id,
		);

		// A run that lost its lease must not write: the request that took the lease stores and sends this entry.
		if ( null !== $holds_lease && ! $holds_lease() ) {
			return self::stop_for_lost_lease( 'insert', $lease_ids, 0, false );
		}

		// Store the response before the send. If the insert fails, the entry is still emailed: a broken table must not lose the entry.
		$row_id = Form_Response_Log::log( $log_args );
		if ( false === $row_id && $logging ) {
			error_log( sprintf( '[prc-block-forms] Form response insert failed for "%s". Sending the email without a stored row.', $form_name ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- VIP routes this to the log stream.
			// Spam with no row is emailed below, so it now counts against the recipient's send limit. A 429 here leaves no row and sends nothing.
			if ( $log_args['is_spam'] ) {
				$throttled = $this->enforce_send_throttle( $target, 'email' );
				if ( is_wp_error( $throttled ) ) {
					return $throttled;
				}
			}
		}

		// The same check again, after the insert and before the send. The send is the step that cannot be taken back, so a run that lost its lease removes its own unsent row and sends nothing.
		if ( null !== $holds_lease && ! $holds_lease() ) {
			$removed = $row_id && 1 === ( new Form_Response_Repository() )->delete( array( (int) $row_id ) );
			return self::stop_for_lost_lease( 'send', $lease_ids, (int) $row_id, $removed );
		}

		// A stored spam row is held back. Spam with no stored row (logging off, or the insert failed) has no Spam folder to wait in, so it is emailed.
		if ( ! $log_args['is_spam'] || ! $row_id ) {
			$report  = array();
			$outcome = self::send_notification_outcome( $target, $form_fields, $form_name, $email_address, $report );
			$status  = self::SEND_SENT === $outcome ? 'sent' : $outcome;

			if ( $row_id && ! ( new Form_Response_Repository() )->set_status( $row_id, $status ) ) {
				error_log( sprintf( '[prc-block-forms] Form response %d could not be set to "%s". It stays "pending".', $row_id, $status ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- VIP routes this to the log stream.
			}

			if ( self::SEND_UNKNOWN === $outcome ) {
				// The entry is stored, and the email may have gone out. Answer success and keep the claim, so a retry never sends it again.
				self::log_send_unknown(
					array(
						'form_id'       => $form_id,
						'form_post_id'  => absint( $form_data['formPostId'] ?? 0 ),
						'submission_id' => $submission_id,
						'response_id'   => (int) $row_id,
						'message_ids'   => $report['message_ids'] ?? array(),
					)
				);
			} elseif ( self::SEND_SENT !== $outcome ) {
				// Logging on, no row, and no email: the entry is nowhere.
				return ( $row_id || ! $logging )
					? new \WP_Error( 'form_submission_failed', 'Form submission failed to send', array( 'status' => 500 ) )
					: new \WP_Error( 'form_submission_not_saved', 'Form submission could not be saved or sent.', array( 'status' => 500 ) );
			}
		}

		return new \WP_REST_Response(
			array(
				'status'     => 'success',
				'message'    => wp_sprintf(
					'%s submitted successfully!',
					$form_name,
				),
				'formFields' => $validated_fields,
			),
			200
		);
	}

	/**
	 * Sign a forward-to recipient for inline (non-synced) forms.
	 *
	 * Baked into actionConfig at render time; verified on submit so the client
	 * cannot swap the recipient without knowing wp_salt( 'auth' ).
	 *
	 * @param string $email Recipient email address.
	 * @return string HMAC-SHA256 hex digest.
	 */
	public static function sign_forward_to( string $email ): string {
		return hash_hmac( 'sha256', strtolower( sanitize_email( $email ) ), wp_salt( 'auth' ) );
	}

	/**
	 * Verify a forward-to signature.
	 *
	 * @param string $email Recipient email address.
	 * @param string $sig   Client-supplied HMAC hex digest.
	 * @return bool
	 */
	public static function verify_forward_to_signature( string $email, string $sig ): bool {
		if ( '' === $email || '' === $sig ) {
			return false;
		}
		$expected = self::sign_forward_to( $email );
		return hash_equals( $expected, $sig );
	}

	/**
	 * Send the form's notification email.
	 *
	 * @param string $target      Validated recipient.
	 * @param array  $form_fields Validated form fields.
	 * @param string $form_name   Form name.
	 * @param string $reply_to    The submitter's address, for the Reply-To header.
	 * @return bool Whether the mailer accepted the message. False also covers an unknown outcome, so a caller that must tell the two apart uses send_notification_outcome().
	 */
	public static function send_notification( string $target, array $form_fields, string $form_name, string $reply_to ): bool {
		return self::SEND_SENT === self::send_notification_outcome( $target, $form_fields, $form_name, $reply_to );
	}

	/**
	 * Send the form's notification email and say how it ended.
	 *
	 * Two scoped changes apply while this send runs, and only for this send:
	 *
	 * - The Mandrill message call is capped at MAIL_TIME_BUDGET seconds, and the ping before it at
	 *   MANDRILL_PING_TIME_BUDGET seconds.
	 * - The Mandrill plugin's native-mailer fallback runs only after a definite non-acceptance. A timeout or a
	 *   lost answer means Mandrill may have accepted the message, and a native copy would be a second email.
	 *   The send ends as SEND_UNKNOWN, and the caller must never send that entry again.
	 *
	 * @param string $target      Validated recipient.
	 * @param array  $form_fields Validated form fields.
	 * @param string $form_name   Form name.
	 * @param string $reply_to    The submitter's address, for the Reply-To header.
	 * @param array  $report      Out: 'message_ids' holds the Mandrill message IDs seen in the answer, if any.
	 * @param string $submitted_gmt When the reader submitted, as a UTC `Y-m-d H:i:s` string. Empty means now.
	 *                              A response emailed later (Not spam) passes the stored time of the entry.
	 * @return string SEND_SENT, SEND_FAILED, or SEND_UNKNOWN.
	 */
	public static function send_notification_outcome( string $target, array $form_fields, string $form_name, string $reply_to, array &$report = array(), string $submitted_gmt = '' ): string {
		// Do not set From — let DEFAULT_EMAIL_ADDRESS / wp_mail_from apply.
		// Reply-To carries the submitter so editors can respond.
		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
		$headers[] = 'Reply-To: ' . sanitize_email( $reply_to );

		$subject = 'New Form Submission from: ' . $form_name;
		$message = self::format_message( $form_fields, $form_name, $submitted_gmt );

		$cap_time = static function ( $request_args, $url ) {
			if ( self::is_mandrill_url( $url ) ) {
				$budget                  = self::is_mandrill_send_url( $url ) ? self::MAIL_TIME_BUDGET : self::MANDRILL_PING_TIME_BUDGET;
				$current                 = isset( $request_args['timeout'] ) ? (float) $request_args['timeout'] : $budget;
				$request_args['timeout'] = min( $current, $budget );
			}
			return $request_args;
		};

		$mandrill          = new \stdClass();
		$mandrill->answer  = '';
		$mandrill->unknown = false;
		$mandrill->ids     = array();
		$watch             = static function ( $response, $context, $transport, $request_args, $url ) use ( $mandrill ) {
			if ( self::is_mandrill_send_url( $url ) ) {
				$mandrill->answer = self::classify_mandrill_answer( $response );
				$mandrill->ids    = array_merge( $mandrill->ids, self::read_mandrill_message_ids( $response ) );
			}
		};
		$native            = array( 'wpMandrill', 'wp_mail_native' );
		$plugin_has        = class_exists( 'wpMandrill', false ) && false !== has_action( 'wp_mail_native', $native );
		$fallback          = static function ( ...$mail_args ) use ( $mandrill, $native ) {
			if ( 'ambiguous' === $mandrill->answer ) {
				$mandrill->unknown = true;
				return;
			}
			$native( ...$mail_args );
		};

		add_filter( 'http_request_args', $cap_time, 99, 2 ); // phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.http_request_args -- this lowers the Mandrill timeouts to the budgets for this one send; it never raises them.
		add_action( 'http_api_debug', $watch, 10, 5 );
		if ( $plugin_has ) {
			remove_action( 'wp_mail_native', $native, 10 );
			add_action( 'wp_mail_native', $fallback, 10, 5 );
		}
		try {
			$accepted = (bool) \wp_mail( $target, $subject, $message, $headers ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail
		} finally {
			remove_filter( 'http_request_args', $cap_time, 99 );
			remove_action( 'http_api_debug', $watch, 10 );
			if ( $plugin_has ) {
				remove_action( 'wp_mail_native', $fallback, 10 );
				add_action( 'wp_mail_native', $native, 10, 5 );
			}
		}

		$report['message_ids'] = array_values( array_unique( $mandrill->ids ) );

		if ( $accepted ) {
			return self::SEND_SENT;
		}
		return $mandrill->unknown ? self::SEND_UNKNOWN : self::SEND_FAILED;
	}

	/**
	 * Read the message IDs from a Mandrill send answer.
	 *
	 * @param mixed $response The http_api_debug response: a WP_Error or a response array.
	 * @return string[] Message IDs, or an empty array when the answer has none.
	 */
	private static function read_mandrill_message_ids( $response ): array {
		if ( is_wp_error( $response ) ) {
			return array();
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return array();
		}
		$ids = array();
		foreach ( $body as $recipient ) {
			if ( is_array( $recipient ) && isset( $recipient['_id'] ) && is_string( $recipient['_id'] ) ) {
				$ids[] = $recipient['_id'];
			}
		}
		return $ids;
	}

	/**
	 * Log a send whose outcome is unknown, with the IDs an admin needs to look it up in Mandrill's activity log.
	 *
	 * The line holds IDs and a time only. It holds no field value and no email address, and it does not hold the
	 * form name, which an author can fill with anything. With form response logging off no row records the entry,
	 * so this line is the only trace of it.
	 *
	 * @param array $ids form_id, form_post_id, submission_id, response_id, message_ids.
	 * @return void
	 */
	public static function log_send_unknown( array $ids ): void {
		$clean       = array( self::class, 'clean_log_value' );
		$message_ids = array_map( $clean, (array) ( $ids['message_ids'] ?? array() ) );
		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- VIP routes this to the log stream.
			sprintf(
				'[prc-block-forms] send_unknown: form_id=%s form_post_id=%d submission_id=%s response_id=%d at=%s mandrill_message_id=%s. The email may or may not have been delivered. It is not retried. Look for this send in the Mandrill activity log.',
				$clean( $ids['form_id'] ?? '' ),
				absint( $ids['form_post_id'] ?? 0 ),
				$clean( $ids['submission_id'] ?? '' ),
				absint( $ids['response_id'] ?? 0 ),
				gmdate( 'c' ),
				array() === $message_ids ? 'none' : implode( ',', $message_ids )
			)
		);
	}

	/**
	 * Keep an ID fit for a log line: plain characters only, at most 64, and `none` when nothing is left.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function clean_log_value( $value ): string {
		$value = substr( (string) preg_replace( '/[^A-Za-z0-9_.:-]/', '', (string) $value ), 0, 64 );
		return '' === $value ? 'none' : $value;
	}

	/**
	 * End a run that lost its lease before it wrote or sent. Logs one line and answers 409.
	 *
	 * The line holds IDs and a time only, as the send_unknown line does: no field value, no address, no form name.
	 * The 409 makes the page wait and post again with the same ID. By then the request that took the lease has
	 * stored and sent the entry, so the retry replays. If nobody took the lease, the retry runs the handler.
	 *
	 * @param string $stage       'insert' (before the row was written) or 'send' (the row was written, nothing was sent).
	 * @param array  $ids         form_id, form_post_id, submission_id.
	 * @param int    $response_id The row this run wrote, or 0.
	 * @param bool   $row_removed Whether this run deleted its own row.
	 * @return \WP_Error The 409 answer.
	 */
	private static function stop_for_lost_lease( string $stage, array $ids, int $response_id, bool $row_removed ): \WP_Error {
		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- VIP routes this to the log stream.
			sprintf(
				'[prc-block-forms] lease_lost: stage=%s form_id=%s form_post_id=%d submission_id=%s response_id=%d row_removed=%s at=%s. This request lost its lease on the submission, so it stopped before the %s. Nothing was sent by this request. The page retries with the same ID.',
				self::clean_log_value( $stage ),
				self::clean_log_value( $ids['form_id'] ?? '' ),
				absint( $ids['form_post_id'] ?? 0 ),
				self::clean_log_value( $ids['submission_id'] ?? '' ),
				$response_id,
				$row_removed ? 'yes' : 'no',
				gmdate( 'c' ),
				'insert' === $stage ? 'insert' : 'send'
			)
		);

		return self::in_progress_error();
	}

	/**
	 * Tell whether the answer to a Mandrill send says the message was refused for certain.
	 *
	 * Definite: the request never reached Mandrill (no DNS answer, no connection, a failed TLS setup), or
	 * Mandrill answered with an API error, or answered 200 with a list that accepts nothing. Anything else
	 * is ambiguous, because Mandrill may have accepted the message: a timeout, a reset, an empty reply, an
	 * answer from a proxy that is not JSON. When unsure, the answer is ambiguous.
	 *
	 * @param mixed $response The http_api_debug response: a WP_Error or a response array.
	 * @return string 'definite' or 'ambiguous'.
	 */
	public static function classify_mandrill_answer( $response ): string {
		if ( is_wp_error( $response ) ) {
			if ( 'http_request_not_executed' === $response->get_error_code() ) {
				return 'definite';
			}
			return preg_match( '/cURL error (\d+):/', $response->get_error_message(), $match ) && in_array( (int) $match[1], self::CURL_NOT_SENT, true )
				? 'definite'
				: 'ambiguous';
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return 'ambiguous';
		}
		if ( 200 !== $code ) {
			return 'error' === ( $body['status'] ?? '' ) ? 'definite' : 'ambiguous';
		}
		foreach ( $body as $recipient ) {
			if ( ! is_array( $recipient ) || in_array( $recipient['status'] ?? '', array( 'sent', 'queued', 'scheduled' ), true ) ) {
				return 'ambiguous';
			}
		}
		return array() === $body ? 'ambiguous' : 'definite';
	}

	/**
	 * Whether a request URL is a Mandrill send (the message endpoints).
	 *
	 * @param mixed $url The request URL.
	 * @return bool
	 */
	private static function is_mandrill_send_url( $url ): bool {
		return self::is_mandrill_url( $url ) && str_contains( (string) wp_parse_url( (string) $url, PHP_URL_PATH ), '/messages/' );
	}

	/**
	 * Whether a request URL goes to the Mandrill API.
	 *
	 * @param mixed $url The request URL.
	 * @return bool
	 */
	private static function is_mandrill_url( $url ): bool {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		return is_string( $host ) && ( 'mandrillapp.com' === $host || str_ends_with( $host, '.mandrillapp.com' ) );
	}

	/**
	 * Extract the configured forwardTo from a form CPT post.
	 *
	 * Walks parsed blocks recursively looking for prc-block/form with
	 * action sendToEmail. Supports legacy redirectUrl-as-email.
	 *
	 * Status rules match Form_Renderer::render_by_id(): publish always;
	 * draft/future/private when the viewer is logged in or in preview.
	 *
	 * @param int $form_post_id Form CPT post ID.
	 * @return string Sanitized email or empty string when not resolvable.
	 */
	public static function extract_forward_to_from_form_post( int $form_post_id ): string {
		if ( $form_post_id <= 0 ) {
			return '';
		}

		$form_post = get_post( $form_post_id );
		if ( ! $form_post instanceof \WP_Post ) {
			return '';
		}

		$expected_type = class_exists( Forms::class ) ? Forms::POST_TYPE : 'form';
		if ( $expected_type !== $form_post->post_type ) {
			return '';
		}

		$allowed_statuses = array( 'publish' );
		if ( is_user_logged_in() || is_preview() ) {
			$allowed_statuses[] = 'draft';
			$allowed_statuses[] = 'future';
			$allowed_statuses[] = 'private';
		}

		if ( ! in_array( $form_post->post_status, $allowed_statuses, true ) ) {
			return '';
		}

		$blocks = parse_blocks( (string) $form_post->post_content );
		return self::find_forward_to_in_blocks( $blocks );
	}

	/**
	 * Recursively find sendToEmail forwardTo in parsed blocks.
	 *
	 * @param array       $blocks    Parsed blocks from parse_blocks().
	 * @param string|null $form_name Only match the form block with this formName. An empty string matches only a form with no name. Null matches the first sendToEmail form.
	 * @return string Sanitized email or empty string.
	 */
	private static function find_forward_to_in_blocks( array $blocks, ?string $form_name = null ): string {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name       = $block['blockName'] ?? '';
			$attrs      = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			$inner      = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();
			$action     = $attrs['action'] ?? '';
			$config     = is_array( $attrs['actionConfig'] ?? null ) ? $attrs['actionConfig'] : array();
			$forward_to = '';

			if ( 'prc-block/form' === $name && 'sendToEmail' === $action && ( null === $form_name || sanitize_text_field( (string) ( $attrs['formName'] ?? '' ) ) === $form_name ) ) {
				$forward_to = isset( $config['forwardTo'] ) ? sanitize_email( (string) $config['forwardTo'] ) : '';
				if ( '' === $forward_to ) {
					$legacy = isset( $attrs['redirectUrl'] ) ? sanitize_email( (string) $attrs['redirectUrl'] ) : '';
					if ( filter_var( $legacy, FILTER_VALIDATE_EMAIL ) ) {
						$forward_to = $legacy;
					}
				}
				if ( filter_var( $forward_to, FILTER_VALIDATE_EMAIL ) ) {
					return $forward_to;
				}
			}

			if ( ! empty( $inner ) ) {
				$nested = self::find_forward_to_in_blocks( $inner, $form_name );
				if ( '' !== $nested ) {
					return $nested;
				}
			}
		}

		return '';
	}

	/**
	 * Resolve the recipient for a stored response.
	 *
	 * Synced forms read forwardTo from the form post, as the submit path does.
	 * Inline forms keep their recipient only in the page that holds the form,
	 * so it is read from the source post, from the sendToEmail form with this name.
	 * A row stored with no source post ID finds the post from its stored source URL.
	 * The stored row holds no recipient, and the client never supplies one here.
	 *
	 * @param array $response A row from Form_Response_Repository::get().
	 * @return string Validated recipient email or empty string.
	 */
	public static function resolve_forward_to_for_response( array $response ): string {
		$form_post_id = absint( $response['form_id'] ?? 0 );
		if ( $form_post_id > 0 ) {
			$target = self::extract_forward_to_from_form_post( $form_post_id );
		} else {
			$source_post_id = absint( $response['source_post_id'] ?? 0 );
			if ( 0 === $source_post_id && ! empty( $response['source_url'] ) && function_exists( 'wpcom_vip_url_to_postid' ) ) {
				$source_post_id = absint( wpcom_vip_url_to_postid( (string) $response['source_url'] ) );
			}
			$source = $source_post_id > 0 ? get_post( $source_post_id ) : null;
			$target = $source instanceof \WP_Post ? self::find_forward_to_in_blocks( parse_blocks( (string) $source->post_content ), sanitize_text_field( (string) ( $response['form_name'] ?? '' ) ) ) : '';
		}
		return filter_var( $target, FILTER_VALIDATE_EMAIL ) ? $target : '';
	}

	/**
	 * Resolve the authoritative sendToEmail recipient.
	 *
	 * Synced forms (formPostId > 0): read forwardTo from the form CPT
	 * (same status rules as Form_Renderer::render_by_id).
	 * Inline forms: require a valid render-time HMAC on actionConfig.forwardToSig.
	 * Never trusts unsigned client forwardTo alone.
	 *
	 * @param array $form_data Decoded submission body.
	 * @return string|\WP_Error Validated recipient email or error.
	 */
	public static function resolve_forward_to( array $form_data ) {
		$form_post_id = absint( $form_data['formPostId'] ?? 0 );

		if ( $form_post_id > 0 ) {
			$target = self::extract_forward_to_from_form_post( $form_post_id );
			if ( ! filter_var( $target, FILTER_VALIDATE_EMAIL ) ) {
				return new \WP_Error(
					'missing_forward_to',
					'No forward-to recipient configured for this form.',
					array( 'status' => 400 )
				);
			}
			return $target;
		}

		$action_config = $form_data['actionConfig'] ?? array();
		if ( ! is_array( $action_config ) ) {
			$action_config = array();
		}

		$claimed = isset( $action_config['forwardTo'] ) ? sanitize_email( (string) $action_config['forwardTo'] ) : '';
		$sig     = isset( $action_config['forwardToSig'] ) ? (string) $action_config['forwardToSig'] : '';

		if ( ! filter_var( $claimed, FILTER_VALIDATE_EMAIL ) ) {
			return new \WP_Error(
				'missing_forward_to',
				'No forward-to recipient configured for this form.',
				array( 'status' => 400 )
			);
		}

		if ( ! self::verify_forward_to_signature( $claimed, $sig ) ) {
			return new \WP_Error(
				'invalid_forward_to_signature',
				'Forward-to recipient signature is invalid.',
				array( 'status' => 403 )
			);
		}

		return $claimed;
	}

	/**
	 * Enforce one send-throttle bucket: per IP, or per recipient.
	 *
	 * @param string $target Validated recipient email address.
	 * @param string $scope  'ip' or 'email'.
	 * @return true|\WP_Error
	 */
	private function enforce_send_throttle( $target, $scope ) {
		if ( ! function_exists( '\\PRC\\Platform\\rate_limit_hit' ) ) {
			return true;
		}

		/**
		 * Filter the send-throttle limits for the sendToEmail form action.
		 *
		 * @param array{ip:array{limit:int,window:int},email:array{limit:int,window:int}} $limits
		 */
		$limits = apply_filters(
			'prc_block_form_send_email_throttle',
			array(
				'ip'    => array(
					'limit'  => self::THROTTLE_IP_LIMIT,
					'window' => self::THROTTLE_IP_WINDOW,
				),
				'email' => array(
					'limit'  => self::THROTTLE_EMAIL_LIMIT,
					'window' => self::THROTTLE_EMAIL_WINDOW,
				),
			)
		);

		if ( 'ip' === $scope ) {
			$ip = function_exists( '\\PRC\\Platform\\get_client_ip' )
				? \PRC\Platform\get_client_ip()
				: '';

			if ( '' !== $ip && isset( $limits['ip']['limit'], $limits['ip']['window'] ) && \PRC\Platform\rate_limit_hit(
				'ip_' . md5( $ip ),
				(int) $limits['ip']['limit'],
				(int) $limits['ip']['window'],
				self::THROTTLE_CACHE_GROUP
			) ) {
				return new \WP_Error( 'rate_limited', 'Too many requests. Please try again later.', array( 'status' => 429 ) );
			}
			return true;
		}

		if ( 'email' === $scope && isset( $limits['email']['limit'], $limits['email']['window'] ) ) {
			if ( \PRC\Platform\rate_limit_hit(
				'to_' . md5( strtolower( $target ) ),
				(int) $limits['email']['limit'],
				(int) $limits['email']['window'],
				self::THROTTLE_CACHE_GROUP
			) ) {
				return new \WP_Error( 'rate_limited', 'This address has reached its send limit. Please try again later.', array( 'status' => 429 ) );
			}
		}

		return true;
	}
}
