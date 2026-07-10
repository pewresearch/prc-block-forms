/**
 * WordPress Dependencies
 */
import { dispatch } from '@wordpress/data';

/**
 * Internal Dependencies
 */
import DEFAULT_FORM_TEMPLATE from './constants';
import MailchimpSubscribeConfigComponent from './action-config/mailchimp-subscribe-config';
import SendToEmailConfigComponent from './action-config/send-to-email-config';
import MailchimpSelectConfigComponent from './action-config/mailchimp-select-config';

const MAILCHIMP_SUBSCRIBE_TEMPLATE = [
	[
		'prc-block/form-input-text',
		{
			type: 'email',
			label: 'Email Address',
			required: true,
			placeholder: 'Enter your email',
			metadata: {
				name: 'emailAddress',
			},
		},
	],
	['prc-block/form-submit', {}],
	[
		'prc-block/form-message',
		{},
		[
			[
				'core/paragraph',
				{
					content: 'Thank you for subscribing!',
				},
			],
		],
	],
];

const MAILCHIMP_SELECT_TEMPLATE = [
	[
		'prc-block/form-input-text',
		{
			type: 'email',
			label: 'Email Address',
			required: true,
			placeholder: 'Enter your email',
			metadata: {
				name: 'emailAddress',
			},
		},
	],
	['prc-block/form-submit', {}],
	[
		'prc-block/form-message',
		{},
		[
			[
				'core/paragraph',
				{
					content: 'Thank you for subscribing!',
				},
			],
		],
	],
];

/**
 * Register the forms with prc-block/form provider.
 */
export default function registerDefaultForms() {
	dispatch('prc-block-library/forms').registerForm({
		label: 'Contact Form',
		description: 'Contact form',
		namespace: 'prc-block/form',
		action: 'sendToEmail',
		method: 'rest',
		template: DEFAULT_FORM_TEMPLATE,
		configDefaults: {
			forwardTo: '',
		},
		ConfigComponent: SendToEmailConfigComponent,
	});

	dispatch('prc-block-library/forms').registerForm({
		label: 'Save Response',
		description:
			'Store submissions in Forms → Responses without sending an email',
		namespace: 'prc-block/form',
		action: 'logResponse',
		method: 'rest',
		template: DEFAULT_FORM_TEMPLATE,
	});

	dispatch('prc-block-library/forms').registerForm({
		label: 'Mailchimp Subscribe',
		description:
			'Subscribe an email address to a Mailchimp newsletter segment',
		namespace: 'prc-block/form',
		action: 'subscribe',
		method: 'api',
		template: MAILCHIMP_SUBSCRIBE_TEMPLATE,
		configDefaults: {
			interest: '',
		},
		ConfigComponent: MailchimpSubscribeConfigComponent,
		supportsRedirect: true,
	});

	dispatch('prc-block-library/forms').registerForm({
		label: 'Newsletter Selection (Mailchimp)',
		description:
			'Let visitors choose from multiple Mailchimp newsletter segments via checkboxes',
		namespace: 'prc-block/form',
		action: 'subscribeSelect',
		method: 'api',
		template: MAILCHIMP_SELECT_TEMPLATE,
		configDefaults: {
			interests: [],
		},
		ConfigComponent: MailchimpSelectConfigComponent,
		supportsRedirect: true,
	});
}

export { MAILCHIMP_SUBSCRIBE_TEMPLATE, MAILCHIMP_SELECT_TEMPLATE };
