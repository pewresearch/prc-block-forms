/**
 * Block Bindings for Form Result Message
 *
 * Provides a binding source so inner paragraph blocks can bind their content
 * to the parent form's runtime result message via the Interactivity API.
 */

/**
 * WordPress Dependencies
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { defineBindingSource } from '@prc/functions';
import { __ } from '@wordpress/i18n';

export default function registerFormMessageBinding() {
	defineBindingSource({
		name: 'prc-block/form-message',
		label: __('Form Result Message', 'prc-block-library'),
		fields: [
			{
				label: __('Form Result Message', 'prc-block-library'),
				type: 'string',
				args: {},
			},
		],
		getValues({ bindings }) {
			const preview = __(
				'Form result message will appear here…',
				'prc-block-library'
			);
			const values = {};

			for (const attributeName of Object.keys(bindings ?? {})) {
				values[attributeName] = preview;
			}

			return values;
		},
		canUserEditValue() {
			return false;
		},
	});

	registerBlockVariation('core/paragraph', {
		name: 'prc-block-form-result-message',
		title: __('Form: Result Message', 'prc-block-library'),
		description: __(
			'Displays the form result message after submission.',
			'prc-block-library'
		),
		attributes: {
			metadata: {
				bindings: {
					content: {
						source: 'prc-block/form-message',
					},
				},
			},
		},
		ancestor: ['prc-block/form-message'],
		scope: ['inserter'],
		isActive: (blockAttributes, variationAttributes) => {
			return (
				blockAttributes.metadata?.bindings?.content?.source ===
				variationAttributes.metadata?.bindings.content.source
			);
		},
	});
}
