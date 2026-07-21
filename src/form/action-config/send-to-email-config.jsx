/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { TextControl } from '@wordpress/components';

/**
 * Config UI for the Contact Form (sendToEmail) action.
 *
 * @param {Object}   props
 * @param {Object}   props.config    Current action config values.
 * @param {Function} props.setConfig Updates a single config key.
 */
export default function SendToEmailConfigComponent({ config, setConfig }) {
	const forwardTo = config?.forwardTo ?? '';

	return (
		<TextControl
			__nextHasNoMarginBottom
			type="email"
			label={__('Forward To', 'form')}
			help={__('Email address where form submissions are sent.', 'form')}
			value={forwardTo}
			onChange={(value) => setConfig('forwardTo', value)}
		/>
	);
}
