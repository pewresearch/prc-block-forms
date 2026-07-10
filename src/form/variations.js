/**
 * External Dependencies
 */
import { Icon } from '@prc/icons';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { registerBlockVariation } from '@wordpress/blocks';

/**
 * Internal Dependencies
 */
import {
	MAILCHIMP_SUBSCRIBE_TEMPLATE,
	MAILCHIMP_SELECT_TEMPLATE,
} from './register-forms';

export default function registerFormVariations() {
	registerBlockVariation('prc-block/form', {
		name: 'newsletter-signup-mailchimp',
		title: __('Newsletter Signup (Mailchimp)', 'form'),
		description: __(
			'A newsletter signup form that subscribes visitors to a Mailchimp segment.',
			'form'
		),
		icon: <Icon icon="mailchimp" library="brands" />,
		keywords: ['mailchimp', 'newsletter', 'subscribe', 'signup'],
		attributes: {
			formName: 'Newsletter Signup',
			method: 'api',
			namespace: 'prc-block/form',
			action: 'subscribe',
			actionConfig: {
				interest: '',
			},
		},
		innerBlocks: MAILCHIMP_SUBSCRIBE_TEMPLATE,
		scope: ['inserter'],
		isActive: (blockAttributes) =>
			blockAttributes?.namespace === 'prc-block/form' &&
			blockAttributes?.action === 'subscribe',
	});

	registerBlockVariation('prc-block/form', {
		name: 'newsletter-selection-mailchimp',
		title: __('Newsletter Selection (Mailchimp)', 'form'),
		description: __(
			'A newsletter signup form where visitors choose from multiple Mailchimp segments.',
			'form'
		),
		icon: <Icon icon="mailchimp" library="brands" />,
		keywords: [
			'mailchimp',
			'newsletter',
			'subscribe',
			'select',
			'checkbox',
		],
		attributes: {
			formName: 'Newsletter Selection',
			method: 'api',
			namespace: 'prc-block/form',
			action: 'subscribeSelect',
			actionConfig: {
				interests: [],
			},
		},
		innerBlocks: MAILCHIMP_SELECT_TEMPLATE,
		scope: ['inserter'],
		isActive: (blockAttributes) =>
			blockAttributes?.namespace === 'prc-block/form' &&
			blockAttributes?.action === 'subscribeSelect',
	});
}
