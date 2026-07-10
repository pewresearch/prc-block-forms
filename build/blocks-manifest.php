<?php
// This file is generated. Do not modify it manually.
return array(
	'form' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'prc-block/form',
		'title' => 'Form',
		'description' => 'A form element with input validation and a centralized form actions registry and api.',
		'category' => 'common',
		'allowedBlocks' => array(
			'core/paragraph',
			'core/heading',
			'core/group',
			'core/columns',
			'core/image',
			'core/button',
			'prc-block/form-input-checkbox',
			'prc-block/form-input-password',
			'prc-block/form-input-radio-group',
			'prc-block/form-input-select',
			'prc-block/form-input-text',
			'prc-block/form-input-textarea',
			'prc-block/form-message',
			'prc-block/form-page',
			'prc-block/form-submit'
		),
		'keywords' => array(
			'form',
			'captcha',
			'field',
			'input'
		),
		'icon' => 'feedback',
		'attributes' => array(
			'formName' => array(
				'type' => 'string'
			),
			'displayMessageEditing' => array(
				'type' => 'boolean',
				'default' => false,
				'role' => 'local'
			),
			'method' => array(
				'type' => 'string',
				'enum' => array(
					'rest',
					'api'
				),
				'default' => 'api'
			),
			'namespace' => array(
				'type' => 'string'
			),
			'action' => array(
				'type' => 'string'
			),
			'redirectUrl' => array(
				'type' => 'string'
			),
			'actionConfig' => array(
				'type' => 'object',
				'default' => array(
					
				)
			)
		),
		'supports' => array(
			'anchor' => true,
			'className' => false,
			'interactivity' => true,
			'color' => array(
				'background' => true,
				'text' => true,
				'link' => true,
				'heading' => true,
				'button' => true
			),
			'layout' => array(
				'type' => 'constrained',
				'default' => array(
					'type' => 'constrained',
					'orientation' => 'vertical',
					'verticalAlignment' => 'center',
					'allowOrientation' => true,
					'contentSize' => '420px'
				),
				'allowSwitching' => true,
				'allowInheriting' => false,
				'allowVerticalAlignment' => true,
				'allowJustification' => true,
				'allowOrientation' => true,
				'allowSizingOnChildren' => true
			),
			'spacing' => array(
				'blockGap' => true,
				'margin' => true,
				'padding' => true,
				'__experimentalDefaultControls' => array(
					'padding' => true,
					'blockGap' => true
				)
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true,
				'__experimentalFontFamily' => true,
				'__experimentalFontWeight' => true,
				'__experimentalFontStyle' => true,
				'__experimentalTextTransform' => true,
				'__experimentalTextDecoration' => true,
				'__experimentalLetterSpacing' => true,
				'__experimentalDefaultControls' => array(
					'fontSize' => true,
					'__experimentalFontFamily' => true
				)
			),
			'__experimentalSelector' => 'form'
		),
		'providesContext' => array(
			'form/displayMessage' => 'displayMessage'
		),
		'usesContext' => array(
			'prc-block/form/formPostId'
		),
		'example' => array(
			'innerBlocks' => array(
				array(
					'name' => 'prc-block/form-input-text',
					'attributes' => array(
						'type' => 'text',
						'label' => 'Name',
						'required' => true,
						'placeholder' => 'Enter your name'
					)
				),
				array(
					'name' => 'prc-block/form-input-text',
					'attributes' => array(
						'type' => 'email',
						'label' => 'Email',
						'required' => true,
						'placeholder' => 'Enter your email'
					)
				),
				array(
					'name' => 'prc-block/form-submit',
					'innerBlocks' => array(
						
					)
				),
				array(
					'name' => 'prc-block/form-message',
					'innerBlocks' => array(
						array(
							'name' => 'core/paragraph',
							'attributes' => array(
								'content' => 'Thank you for your message!'
							)
						)
					)
				)
			)
		),
		'textdomain' => 'form',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php',
		'viewScriptModule' => 'file:./view/index.js'
	),
	'form-captcha' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'prc-block/form-captcha',
		'version' => '1.0.3',
		'title' => 'Form Captcha',
		'category' => 'forms',
		'description' => 'Display a captcha form element. Powered by Cloudflare Turnstile. This Captcha is mostly invisible and does not require user interaction.',
		'attributes' => array(
			
		),
		'example' => array(
			
		),
		'supports' => array(
			'anchor' => false,
			'html' => false,
			'spacing' => array(
				'margin' => array(
					'top',
					'bottom'
				)
			),
			'interactivity' => true
		),
		'textdomain' => 'form-captcha',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'viewScriptModule' => 'file:./view.js'
	),
	'form-message' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'prc-block/form-message',
		'version' => '1.0.0',
		'title' => 'Form Message',
		'category' => 'forms',
		'description' => 'Display a message to the user upon successful form submission.',
		'allowedBlocks' => array(
			'core/paragraph',
			'core/heading',
			'core/group',
			'core/buttons',
			'core/button',
			'core/separator',
			'core/image',
			'prc-block/form-input-text'
		),
		'attributes' => array(
			
		),
		'supports' => array(
			'anchor' => false,
			'html' => false,
			'reusable' => true,
			'interactivity' => true,
			'spacing' => array(
				'blockGap' => true,
				'margin' => array(
					'top',
					'bottom'
				),
				'padding' => true,
				'__experimentalDefaultControls' => array(
					'padding' => true
				)
			),
			'color' => array(
				'background' => true,
				'text' => true,
				'link' => true,
				'button' => true
			),
			'typography' => array(
				'fontSize' => true,
				'__experimentalFontFamily' => true,
				'__experimentalDefaultControls' => array(
					'fontSize' => true,
					'__experimentalFontFamily' => true
				)
			)
		),
		'example' => array(
			'innerBlocks' => array(
				array(
					'name' => 'core/paragraph',
					'attributes' => array(
						'content' => 'Thank you for your submission!'
					)
				)
			)
		),
		'textdomain' => 'form-message',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css'
	),
	'form-message-bindings' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'prc-block/form-message-bindings',
		'version' => '1.0.0',
		'title' => 'Form Message Bindings',
		'textdomain' => 'prc-block-library',
		'editorScript' => 'file:./index.js'
	),
	'form-page' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'prc-block/form-page',
		'version' => '1.0.1',
		'title' => 'Form Page',
		'description' => 'A primitive block for a form page',
		'category' => 'forms',
		'ancestor' => array(
			'prc-block/form'
		),
		'example' => array(
			'innerBlocks' => array(
				array(
					'name' => 'core/paragraph',
					'attributes' => array(
						'content' => 'Form page content.'
					)
				)
			)
		),
		'supports' => array(
			'anchor' => true,
			'html' => false,
			'reusable' => false,
			'interactivity' => true,
			'color' => array(
				'background' => true,
				'text' => true,
				'link' => true
			),
			'layout' => array(
				'type' => 'flex',
				'default' => array(
					'type' => 'flex',
					'orientation' => 'vertical',
					'verticalAlignment' => 'center',
					'allowOrientation' => true
				),
				'allowInheriting' => false,
				'allowVerticalAlignment' => true,
				'allowJustification' => true,
				'allowOrientation' => true,
				'allowSizingOnChildren' => true
			),
			'spacing' => array(
				'blockGap' => true,
				'padding' => true,
				'margin' => true
			),
			'typography' => array(
				'fontSize' => true,
				'lineHeight' => true,
				'__experimentalFontFamily' => true,
				'__experimentalFontWeight' => true
			)
		),
		'textdomain' => 'form-page',
		'editorScript' => 'file:./index.js',
		'style' => 'file:./style-index.css'
	),
	'form-submit' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'prc-block/form-submit',
		'title' => 'Form Submit Actions',
		'category' => 'design',
		'icon' => 'button',
		'ancestor' => array(
			'prc-block/form'
		),
		'allowedBlocks' => array(
			'core/button',
			'prc-block/form-captcha'
		),
		'description' => 'Submission actions for forms. Includes submit button, captcha, and optional response message.',
		'keywords' => array(
			'submit',
			'button',
			'form'
		),
		'supports' => array(
			'anchor' => true,
			'spacing' => array(
				'margin' => array(
					'top',
					'bottom'
				),
				'padding' => true,
				'__experimentalDefaultControls' => array(
					'padding' => true
				)
			)
		),
		'usesContext' => array(
			'form/displayMessage'
		),
		'example' => array(
			'innerBlocks' => array(
				array(
					'name' => 'core/button',
					'attributes' => array(
						'text' => 'Submit',
						'tagName' => 'button',
						'type' => 'submit'
					)
				),
				array(
					'name' => 'prc-block/form-captcha',
					'attributes' => array(
						
					)
				)
			)
		),
		'textdomain' => 'form-submit',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css'
	),
	'synced-form' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'prc-block/synced-form',
		'title' => 'Synced Form',
		'category' => 'forms',
		'description' => 'Create, save, and sync forms to reuse across the site. Update the form, and the changes apply everywhere it\'s used. Responses are collected centrally under Forms → Responses.',
		'keywords' => array(
			'form',
			'synced',
			'reusable',
			'embed'
		),
		'textdomain' => 'synced-form',
		'attributes' => array(
			'ref' => array(
				'type' => 'number'
			)
		),
		'supports' => array(
			'customClassName' => false,
			'html' => false,
			'align' => true,
			'interactivity' => true
		),
		'providesContext' => array(
			'prc-block/form/formPostId' => 'ref'
		),
		'editorScript' => 'file:./index.js'
	)
);
