/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { TextControl } from '@wordpress/components';

/**
 * Fallback config UI when a registered action declares configDefaults but no
 * custom ConfigComponent.
 *
 * @param {Object}   props
 * @param {Object}   props.config       Current action config values.
 * @param {Function} props.setConfig      Updates a single config key.
 * @param {Object}   props.configDefaults Default keys from the registry entry.
 */
export default function DefaultActionConfigComponent({
	config,
	setConfig,
	configDefaults = {},
}) {
	const keys = Object.keys(configDefaults);

	if (keys.length === 0) {
		return null;
	}

	return (
		<>
			{keys.map((key) => (
				<TextControl
					key={key}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={key}
					value={config?.[key] ?? ''}
					onChange={(value) => setConfig(key, value)}
					help={__(
						'Action configuration value passed to the submission handler.',
						'form'
					)}
				/>
			))}
		</>
	);
}
