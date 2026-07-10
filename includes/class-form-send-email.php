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
	private function format_body( $form_fields, $form_name ) {
		$message = '';
		foreach ( $form_fields as $index => $field ) {
			// Skip system fields like captcha and nonce tokens.
			if ( in_array( $field['type'], array( 'captchaToken', 'nonceToken' ), true ) ) {
				continue;
			}

			$label = isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : 'Unlabeled Field';
			// if the label is an empty string, set it to the field name
			if ( empty( $label ) ) {
				$label = sanitize_text_field( $field['name'] );
			}

			$value = sanitize_text_field( $field['value'] );

			// Handle checkbox values.
			if ( 'checkbox' === $field['type'] ) {
				$value = isset( $field['checked'] ) && $field['checked'] ? 'Yes' : 'No';
			}

			if ( 'radio' === $field['type'] ) {
				// if not checked, skip
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
	 * @return string The formatted HTML message.
	 */
	private function format_message( $form_fields, $form_name ) {
		$form_name = sanitize_text_field( $form_name );

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

		$message .= $this->format_body( $form_fields, $form_name );

		$message .= '
					</tbody>
				</table>
				<div style="margin-top: 30px; padding: 15px; background-color: #ecf0f1; border-left: 4px solid #3498db; color: #2c3e50;">
					<p style="margin: 0; font-size: 14px;"><strong>Submission Time:</strong> ' . current_time( 'F j, Y, g:i a' ) . '</p>
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
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_email_submission( $request ) {
		$form_data = json_decode( $request->get_body(), true );

		if ( ! $form_data || ! is_array( $form_data ) ) {
			return new \WP_Error( 'invalid_form_data', 'Invalid form data provided.', array( 'status' => 400 ) );
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

		$subject = 'New Form Submission from: ' . $form_name;
		$message = $this->format_message( $form_fields, $form_name );

		$throttled = $this->enforce_send_throttle( $target );
		if ( is_wp_error( $throttled ) ) {
			return $throttled;
		}

		// Do not set From — let DEFAULT_EMAIL_ADDRESS / wp_mail_from apply.
		// Reply-To carries the submitter so editors can respond.
		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
		$headers[] = 'Reply-To: ' . sanitize_email( $email_address );

		$sent = \wp_mail( $target, $subject, $message, $headers ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail

		// Log the response either way — a failed send should never lose the submission.
		if ( class_exists( '\PRC\Platform\Block_Forms\Form_Response_Log' ) ) {
			Form_Response_Log::log(
				array(
					'form_post_id' => absint( $form_data['formPostId'] ?? 0 ),
					'form_name'    => $form_name,
					'action'       => 'sendToEmail',
					'status'       => $sent ? 'sent' : 'send_failed',
					'fields'       => $validated_fields,
				)
			);
		}

		if ( $sent ) {
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
		} else {
			return new \WP_Error( 'form_submission_failed', 'Form submission failed to send', array( 'status' => 500 ) );
		}
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
	 * @param array $blocks Parsed blocks from parse_blocks().
	 * @return string Sanitized email or empty string.
	 */
	private static function find_forward_to_in_blocks( array $blocks ): string {
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

			if ( 'prc-block/form' === $name && 'sendToEmail' === $action ) {
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
				$nested = self::find_forward_to_in_blocks( $inner );
				if ( '' !== $nested ) {
					return $nested;
				}
			}
		}

		return '';
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
	 * Enforce per-IP and per-recipient send throttling.
	 *
	 * @param string $target Validated recipient email address.
	 * @return true|\WP_Error
	 */
	private function enforce_send_throttle( $target ) {
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

		$ip = function_exists( '\\PRC\\Platform\\get_client_ip' )
			? \PRC\Platform\get_client_ip()
			: '';

		if ( '' !== $ip && isset( $limits['ip']['limit'], $limits['ip']['window'] ) ) {
			if ( \PRC\Platform\rate_limit_hit(
				'ip_' . md5( $ip ),
				(int) $limits['ip']['limit'],
				(int) $limits['ip']['window'],
				self::THROTTLE_CACHE_GROUP
			) ) {
				return new \WP_Error( 'rate_limited', 'Too many requests. Please try again later.', array( 'status' => 429 ) );
			}
		}

		if ( isset( $limits['email']['limit'], $limits['email']['window'] ) ) {
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
