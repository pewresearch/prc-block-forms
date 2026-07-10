/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Human-readable label for a registered form action's submission method.
 *
 * @param {string} method Registry method value.
 * @return {string}
 */
export function getActionMethodLabel(method) {
	switch (method) {
		case 'rest':
			return __('Server (REST)', 'form');
		case 'api':
			return __('In-page (Interactivity API)', 'form');
		case 'server':
			return __('Server', 'form');
		default:
			return method;
	}
}

/**
 * Help text shown under the unified action picker.
 *
 * @return {string}
 */
export function getActionPickerHelpText() {
	return __(
		'Server actions POST submissions to a REST endpoint (/wp-json/prc-api/v3/form/*). In-page actions run a client-side Interactivity API handler in the browser without a dedicated form endpoint.',
		'form'
	);
}

/**
 * Whether a registry entry should appear in the action picker.
 *
 * @param {Object} form              Registered form entry.
 * @param {string} rootBlockNamespace Parent block namespace for server-scoped actions.
 * @param {string} currentActionKey   Current namespace::action value, if any.
 * @return {boolean}
 */
export function isActionVisibleInPicker(
	form,
	rootBlockNamespace,
	currentActionKey
) {
	const actionKey = `${form.namespace}::${form.action}`;

	if (form.hidden && actionKey !== currentActionKey) {
		return false;
	}

	if (form.method === 'server') {
		return form.namespace === rootBlockNamespace;
	}

	if (form.method === 'rest') {
		return (
			form.namespace === 'prc-block/form' ||
			form.namespace === rootBlockNamespace
		);
	}

	// API actions are globally available (form CPT, synced forms, etc.).
	if (form.method === 'api') {
		return true;
	}

	return false;
}

/**
 * Build a flat SelectControl options list for all visible registered actions.
 *
 * `SelectControl` (in the platform WP build) does not support nested option
 * groups via a `{ label, options: [] }` shape — it renders such entries as a
 * single valueless option and drops the children. So we return a flat list and
 * denote the submission type in each option's own label instead.
 *
 * @param {Array}  registeredForms       Forms from the registry store.
 * @param {string} rootBlockNamespace    Parent block namespace.
 * @param {string} currentActionKey      Current namespace::action value.
 * @return {Array}
 */
export function buildActionSelectOptions(
	registeredForms,
	rootBlockNamespace,
	currentActionKey
) {
	const visible = registeredForms.filter((form) =>
		isActionVisibleInPicker(form, rootBlockNamespace, currentActionKey)
	);

	const restActions = visible.filter((form) => form.method === 'rest');
	const apiActions = visible.filter((form) => form.method === 'api');
	const serverActions = visible.filter((form) => form.method === 'server');

	const toOption = (form) => ({
		label: `${form.label} — ${getActionMethodLabel(form.method)}`,
		value: `${form.namespace}::${form.action}`,
	});

	const orderedOptions = [
		...restActions.map(toOption),
		...apiActions.map(toOption),
		...serverActions.map(toOption),
	];

	if (orderedOptions.length === 0) {
		return [
			{
				label: __('No actions available', 'form'),
				value: '',
			},
		];
	}

	return [
		{
			label: __('Select an action', 'form'),
			value: '',
		},
		...orderedOptions,
	];
}

/**
 * Resolve a registry entry by namespace and action.
 *
 * @param {Array}  registeredForms Registry forms.
 * @param {string} namespace       Action namespace.
 * @param {string} action          Action name.
 * @return {Object|undefined}
 */
export function findRegisteredAction(registeredForms, namespace, action) {
	return registeredForms.find(
		(form) => form.namespace === namespace && form.action === action
	);
}
