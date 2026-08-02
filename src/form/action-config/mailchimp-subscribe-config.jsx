/**
 * External Dependencies
 */
import { MailchimpSegmentSelect } from '@prc/components';

/**
 * WordPress Dependencies
 */
import { PanelRow } from '@wordpress/components';

/**
 * Config UI for the Mailchimp Subscribe form action.
 *
 * @param {Object}   props
 * @param {Object}   props.config    Current action config values.
 * @param {Function} props.setConfig Updates a config key or patch object.
 */
export default function MailchimpSubscribeConfigComponent({
	config,
	setConfig,
}) {
	const audienceId = config?.audienceId ?? '';
	const segmentId = config?.segmentId ?? '';

	return (
		<PanelRow>
			<MailchimpSegmentSelect
				audienceId={audienceId}
				segmentId={segmentId}
				onAudienceChange={(nextAudienceId) =>
					setConfig({
						audienceId: nextAudienceId,
						segmentId: '',
					})
				}
				onSegmentChange={(nextSegmentId) =>
					setConfig('segmentId', nextSegmentId)
				}
			/>
		</PanelRow>
	);
}
