/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { TextControl } from '@wordpress/components';

/**
 * Shared redirect URL config rendered when a registry entry sets supportsRedirect.
 *
 * @param {Object}   props
 * @param {Object}   props.config    Current action config values.
 * @param {Function} props.setConfig Updates a single config key.
 */
export default function RedirectUrlConfigComponent({ config, setConfig }) {
	const redirectUrl = config?.redirectUrl ?? '';

	return (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={__('Redirect URL', 'form')}
			help={
				/* translators: %field_name% is a placeholder for a form field name. */
				__(
					'Optional URL to redirect to after a successful submission. You can pass values in the form of %field_name% to insert the value of a form field into the URL.',
					'form'
				)
			}
			value={redirectUrl}
			onChange={(value) => setConfig('redirectUrl', value)}
		/>
	);
}
