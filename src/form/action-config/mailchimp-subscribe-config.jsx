/**
 * External Dependencies
 */
import { MailchimpSegmentSelect } from '@prc/components';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { PanelRow } from '@wordpress/components';

/**
 * Config UI for the Mailchimp Subscribe form action.
 *
 * @param {Object}   props
 * @param {Object}   props.config    Current action config values.
 * @param {Function} props.setConfig Updates a single config key.
 */
export default function MailchimpSubscribeConfigComponent({
	config,
	setConfig,
}) {
	const interest = config?.interest ?? '';

	return (
		<PanelRow>
			<MailchimpSegmentSelect
				label={__('Choose Newsletter Segment', 'form')}
				value={interest}
				onChange={(newInterestId) =>
					setConfig('interest', newInterestId)
				}
				apiKey="mailchimp-form"
			/>
		</PanelRow>
	);
}
