/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	__experimentalText as Text,
	__experimentalVStack as VStack,
} from '@wordpress/components';

function getLocalizedData() {
	return window?.prcFormResponses || {};
}

/**
 * Elements for the Form filter: form CPT posts first, then legacy inline
 * form names logged before those forms were migrated to the CPT.
 */
function getFormFilterElements() {
	const { forms = [], legacyFormNames = [] } = getLocalizedData();
	return [
		...forms.map((form) => ({
			value: `id:${form.id}`,
			label: form.title,
		})),
		...legacyFormNames.map((name) => ({
			value: `name:${name}`,
			label: `${name} (${__('inline', 'prc-block-forms')})`,
		})),
	];
}

function getStatusFilterElements() {
	const { statuses = [] } = getLocalizedData();
	return statuses.map((status) => ({
		value: status,
		label: status,
	}));
}

function getFormLabel(item) {
	return item?.formTitle || item?.formName || '';
}

const fields = [
	{
		id: 'from',
		type: 'text',
		label: __('From', 'prc-block-forms'),
		getValue: ({ item }) => item?.fromName || item?.email || '',
		render: ({ item }) => (
			<VStack spacing={0}>
				<Text weight={500}>
					{item?.fromName ||
						item?.email ||
						__('—', 'prc-block-forms')}
				</Text>
				{!!item?.fromName && !!item?.email && (
					<Text variant="muted">{item.email}</Text>
				)}
			</VStack>
		),
		enableGlobalSearch: true,
		enableSorting: false,
		enableHiding: false,
	},
	{
		id: 'form',
		label: __('Form', 'prc-block-forms'),
		getValue: ({ item }) => getFormLabel(item),
		render: ({ item }) => {
			const label = getFormLabel(item);
			if (!label) {
				return <span>{__('—', 'prc-block-forms')}</span>;
			}
			if (item?.formEditUrl) {
				return <a href={item.formEditUrl}>{label}</a>;
			}
			return <span>{label}</span>;
		},
		elements: getFormFilterElements(),
		filterBy: {
			operators: ['is'],
			isPrimary: true,
		},
		enableSorting: false,
	},
	{
		id: 'action',
		type: 'text',
		label: __('Action', 'prc-block-forms'),
		getValue: ({ item }) => item?.action || '',
		enableSorting: false,
	},
	{
		id: 'isUnread',
		label: __('Unread', 'prc-block-forms'),
		getValue: ({ item }) => (item?.isUnread ? 'unread' : 'read'),
		render: ({ item }) =>
			item?.isUnread
				? __('Unread', 'prc-block-forms')
				: __('Read', 'prc-block-forms'),
		elements: [
			{ value: true, label: __('Unread', 'prc-block-forms') },
			{ value: false, label: __('Read', 'prc-block-forms') },
		],
		filterBy: {
			operators: ['is'],
		},
		enableSorting: false,
	},
	{
		id: 'status',
		label: __('Status', 'prc-block-forms'),
		getValue: ({ item }) => item?.status || '',
		elements: getStatusFilterElements(),
		filterBy: {
			operators: ['isAny'],
		},
		enableSorting: false,
	},
	{
		id: 'source',
		label: __('Source', 'prc-block-forms'),
		getValue: ({ item }) => item?.sourceTitle || item?.sourceUrl || '',
		render: ({ item }) => {
			if (!item?.sourceUrl) {
				return <span>{__('—', 'prc-block-forms')}</span>;
			}
			let label = item?.sourceTitle;
			if (!label) {
				try {
					label = new URL(item.sourceUrl).pathname;
				} catch {
					label = item.sourceUrl;
				}
			}
			return (
				<a href={item.sourceUrl} target="_blank" rel="noreferrer">
					{label}
				</a>
			);
		},
		enableSorting: false,
	},
	{
		id: 'date',
		type: 'datetime',
		label: __('Date', 'prc-block-forms'),
		getValue: ({ item }) => item?.date || '',
		enableSorting: true,
	},
];

export default fields;
