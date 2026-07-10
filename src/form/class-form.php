<?php
/**
 * Form Block
 *
 * @package PRC\Platform\Block_Forms
 */

namespace PRC\Platform\Block_Forms;

use WP_Block_Type_Registry, WP_HTML_Tag_Processor;

/**
 * Block Name:        Form
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Seth Rubenstein
 *
 * @package           prc-block
 */
class Form {
	/**
	 * Constructor
	 *
	 * @param mixed $loader Loader.
	 */
	public function __construct( $loader ) {
		$this->init( $loader );
	}

	/**
	 * Initialize the block
	 *
	 * @param mixed $loader Loader.
	 */
	public function init( $loader = null ) {
		if ( null !== $loader ) {
			$loader->add_action( 'init', $this, 'block_init' );
			$loader->add_filter( 'allowed_block_types_all', $this, 'disable_other_form_blocks', 10, 2 );
			$loader->add_filter( 'render_block', $this, 'handle_conditional_form_field_display', 10, 2 );
		}
	}

	/**
	 * Wraps inner results blocks with display logic dependent on score.
	 *
	 * @hook render_block
	 *
	 * @param string $block_content The block content.
	 * @param array  $block The block data.
	 * @return string
	 */
	public function handle_conditional_form_field_display( $block_content, $block ) {
		if ( ! isset( $block['attrs']['formDisplayMode'] ) ) {
			return $block_content;
		}
		$display_mode = $block['attrs']['formDisplayMode'];
		if ( 'always' === $display_mode ) {
			return $block_content;
		}

		$form_display_condition = $block['attrs']['formDisplayCondition'] ?? false;
		if ( ! $form_display_condition || ! is_array( $form_display_condition ) ) {
			return $block_content;
		}

		$tag = new WP_HTML_Tag_Processor( $block_content );
		$tag->next_tag();
		// Check if this block has a data-wp-interactive attribute already, if so we need to wrap it in a new div to contain it's interactivity context.
		if ( $tag->get_attribute( 'data-wp-interactive' ) ) {
			$tag->get_updated_html();
			$content = wp_sprintf(
				'<div data-wp-interactive="prc-block/form">%s</div>',
				$block_content
			);
			// If New Relic is available, add a custom tracer or log a custom event for transaction tracing.
			\PRC\Platform\Newrelic\trace( 'prc-block/form/handle_conditional_form_field_display', 'wrapped_interactive_block' );
			// Reset the tag processor.
			$tag = new WP_HTML_Tag_Processor( $content );
			$tag->next_tag();
		}

		$tag->set_attribute(
			'data-wp-context',
			wp_json_encode(
				array(
					'formDisplayCondition' => $form_display_condition,
				)
			)
		);
		$tag->set_attribute( 'data-wp-bind--hidden', '!state.formDisplayCondition' );

		return $tag->get_updated_html();
	}

	/**
	 * Filter the allowed blocks in the editor.
	 *
	 * @hook allowed_block_types_all
	 *
	 * @internal
	 * @param array|bool $allowed_block_types Array of allowed block types or a boolean.
	 * @param object     $editor_context The editor context.
	 * @return array Array of allowed block types.
	 */
	public function disable_other_form_blocks( $allowed_block_types, $editor_context ) {
		$registry         = WP_Block_Type_Registry::get_instance();
		$registerd_blocks = $registry->get_all_registered();
		$registerd_blocks = array_keys( $registerd_blocks );

		$blocks_to_remove = array(
			'jetpack/contact-form',
		);

		$allowed_block_types = array_diff( $registerd_blocks, $blocks_to_remove );
		$allowed_block_types = array_values( $allowed_block_types );

		return $allowed_block_types;
	}

	/**
	 * Get form endpoints
	 *
	 * Sets the default email endpoint and allows for additional endpoints to be added.
	 *
	 * @return array
	 */
	public function get_form_endpoints() {
		return apply_filters(
			'prc_platform_form_endpoints',
			array()
		);
	}

	/**
	 * Get API action
	 *
	 * @param string $namespace_prefix Namespace prefix.
	 * @param string $action Action.
	 * @return string
	 */
	public function get_api_action( $namespace_prefix, $action ) {
		return $namespace_prefix . '::' . $action;
	}

	/**
	 * Get server action
	 *
	 * @param string $namespace_prefix Namespace prefix.
	 * @param string $action Action.
	 * @return string
	 */
	public function get_server_action( $namespace_prefix, $action ) {
		return $this->get_api_action( $namespace_prefix, $action );
	}

	/**
	 * Get REST action
	 *
	 * @param string $namespace_prefix Namespace prefix.
	 * @param string $action Action.
	 * @return string
	 */
	public function get_rest_action( $namespace_prefix, $action ) {
		return $this->get_api_action( $namespace_prefix, $action );
	}

	/**
	 * Render the errors
	 *
	 * @return string
	 */
	public function render_errors() {
		return wp_sprintf(
			'<div class="wp-block-prc-block-form-errors"><template data-wp-each--error="context.errors"><mark class="wp-block-prc-block-form-error"><button data-wp-text="context.error.message" data-wp-on--click="actions.onErrorClick" data-wp-bind--data-action-url="context.error.actionUrl" type="button"></button></mark></template></div>',
		);
	}

	/**
	 * Render the form callback
	 *
	 * @param array     $attributes Attributes.
	 * @param string    $content Content.
	 * @param \WP_Block $block Block instance.
	 * @return string
	 */
	public function render_form_callback( $attributes, $content, $block = null ) {
		// When rendered through a prc-block/synced-form block, the form CPT
		// post ID travels down via block context so submissions can be tied
		// back to their form.
		$form_post_id = 0;
		if ( $block instanceof \WP_Block && isset( $block->context['prc-block/form/formPostId'] ) ) {
			$form_post_id = absint( $block->context['prc-block/form/formPostId'] );
		}
		$form_method    = $attributes['method'] ?? false;
		$form_action    = $attributes['action'] ?? false;
		$form_namespace = $attributes['namespace'] ?? false;
		if ( empty( $form_method ) || empty( $form_action ) || empty( $form_namespace ) ) {
			do_action( 'qm/warning', 'Form block misconfigured: missing method, action, or namespace.' );
			return '<p>Form misconfigured.</p>';
		}
		if ( 'rest' === $form_method ) {
			wp_enqueue_script( 'wp-api-fetch' );
			wp_enqueue_script( 'wp-url' );
		}

		$action_config = $attributes['actionConfig'] ?? array();
		if ( ! is_array( $action_config ) ) {
			$action_config = array();
		}
		// Backwards-compat: before the `actionConfig` object existed, forms stored a
		// single top-level `redirectUrl` attribute. For contact forms it held the
		// recipient email; for every other action it held the post-submit redirect URL.
		$legacy_redirect = $attributes['redirectUrl'] ?? '';
		if ( is_string( $legacy_redirect ) && '' !== $legacy_redirect ) {
			if ( 'sendToEmail' === $form_action ) {
				if ( empty( $action_config['forwardTo'] ) ) {
					$action_config['forwardTo'] = $legacy_redirect;
				}
			} elseif ( empty( $action_config['redirectUrl'] ) ) {
				// Legacy `/` meant "redirect back to the current URL".
				if ( '/' === $legacy_redirect ) {
					$legacy_redirect = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
				}
				if ( '' !== $legacy_redirect ) {
					$action_config['redirectUrl'] = $legacy_redirect;
				}
			}
		}

		// Bake an HMAC over forwardTo so inline (non-synced) submissions cannot
		// swap the recipient. Synced forms resolve forwardTo from the form CPT
		// on submit and ignore the client value; the sig is still harmless there.
		if ( 'sendToEmail' === $form_action ) {
			$forward_to = isset( $action_config['forwardTo'] ) ? sanitize_email( (string) $action_config['forwardTo'] ) : '';
			if ( filter_var( $forward_to, FILTER_VALIDATE_EMAIL ) ) {
				$action_config['forwardTo']    = $forward_to;
				$action_config['forwardToSig'] = Form_Send_Email::sign_forward_to( $forward_to );
			} else {
				unset( $action_config['forwardToSig'] );
			}
		}

		$tag = new \WP_HTML_Tag_Processor( $content );
		$tag->next_tag( 'form' );

		// Get and set the id if it doesn't exist.
		$block_id = $tag->get_attribute( 'id' );
		if ( ! $block_id ) {
			$block_id = wp_unique_id( 'prc-block-form-' );
			$tag->set_attribute( 'id', $block_id );
		}
		// Define the interactivity namespace.
		$tag->set_attribute( 'data-wp-interactive', 'prc-block/form' );

		// Events.
		$tag->set_attribute( 'data-wp-init', 'callbacks.onFormMount' );
		$tag->set_attribute( 'data-wp-on--submit', 'actions.onSubmit' );
		$tag->set_attribute( 'data-wp-class--has-errors', 'state.hasErrors' );
		$tag->set_attribute( 'data-wp-class--is-displaying-form-message', 'state.formMessage' );
		$tag->set_attribute( 'data-wp-class--is-processing', 'context.submissionProcessing' );
		$tag->set_attribute( 'data-wp-watch--onCaptchaPassing', 'callbacks.onCaptchaPassing' );
		$tag->set_attribute( 'data-wp-watch--sendSubmission', 'callbacks.sendSubmission' );
		$tag->set_attribute( 'data-wp-watch--onProcessing', 'callbacks.onProcessing' );

		// Rewind.
		$tag->set_bookmark( 'form_start' );

		$form_pages = array();
		while ( $tag->next_tag(
			array(
				'class_name' => 'wp-block-prc-block-form-page',
			)
		) ) {
			$form_pages[] = $tag->get_attribute( 'id' );
		}

		// Rewind.
		$tag->seek( 'form_start' );

		// $button_text = null;

		// while ( $tag->next_tag(
		// array(
		// 'tag_name' => 'button',
		// )
		// ) ) {
		// if ( 'submit' === $tag->get_attribute( 'type' ) ) {
		// $button_text = \PRC\Platform\Blocks\Core_Button::get_button_text( $content );
		// $tag->set_attribute( 'data-wp-bind--disabled', 'prc-block/form::state.submissionDisabled' );
		// if ( null !== $button_text ) {
		// $tag->set_attribute( 'data-wp--text', 'prc-block/form::state.submitButtonText' );
		// }
		// }
		// }

		// // Rewind.
		// $tag->seek( 'form_start' );

		// Define the interactivity context.
		$tag->set_attribute(
			'data-wp-context',
			wp_json_encode(
				array(
					'formId'               => $block_id,
					'formPostId'           => $form_post_id,
					'formName'             => $attributes['formName'] ?? get_the_title() . ' Form',
					'errors'               => array(),
					'captchaPassed'        => false,
					'captchaHidden'        => true,
					'captchaToken'         => '',
					'nonceName'            => 'prc-block-form',
					'nonceToken'           => '',
					'stopProcessing'       => false,
					'submissionProcessing' => false,
					'allowSubmit'          => true,
					'formMessage'          => false,
					'submitButtonText'     => null,
					'submitMethod'         => array(
						'method'    => $form_method,
						'action'    => $form_action,
						'namespace' => $form_namespace,
					),
					'actionConfig'         => $action_config,
					'formPages'            => empty( $form_pages ) ? false : $form_pages,
					'activePage'           => empty( $form_pages ) ? false : $form_pages[0],
				)
			)
		);

		$content = $tag->get_updated_html();

		// Add errors and processing spinner to the end of the form.
		$regex       = '/<\/form>/';
		$replacement = $this->render_errors() . '<div class="wp-block-prc-block-form-processing"><div class="wp-block-prc-block-form-processing_spinner"><span>Processing...</span></div></div></form>';
		return preg_replace( $regex, $replacement, $content );
	}

	/**
	 * Registers the block using the metadata loaded from the `block.json` file.
	 * Behind the scenes, it registers also all assets so they can be enqueued
	 * through the block editor in the corresponding context.
	 *
	 * @hook init
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_block_type/
	 */
	public function block_init() {
		register_block_type_from_metadata(
			PRC_BLOCK_FORMS_DIR . '/build/form',
			array(
				'render_callback' => array( $this, 'render_form_callback' ),
			)
		);
	}
}
