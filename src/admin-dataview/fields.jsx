import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { inbox } from '@wordpress/icons';

function getFormsData() {
	return window?.prcWpAdminDataview?.forms || {};
}

function getElements(key) {
	return (getFormsData()[key] || []).map((option) => ({
		value: option.value,
		label: option.label,
	}));
}

function getOptionLabel(key, value) {
	const match = (getFormsData()[key] || []).find(
		(option) => option.value === value
	);
	return match?.label || value || '—';
}

export function getDefaultVisibleFields(canViewResponses) {
	if (!canViewResponses) {
		return ['action', 'status', 'date'];
	}
	return ['action', 'responses', 'unread', 'status', 'date'];
}

export default function getFormFields({
	onOpenResponses,
	canViewResponses = false,
} = {}) {
	const fields = [
		{
			id: 'action',
			label: __('Action', 'prc-block-forms'),
			getValue: ({ item }) => item?.action || '',
			render: ({ item }) => (
				<span>{getOptionLabel('actions', item?.action)}</span>
			),
			elements: getElements('actions'),
			filterBy: {
				operators: ['isAny'],
				isPrimary: true,
			},
			enableSorting: false,
		},
		{
			id: 'responses',
			label: __('Responses', 'prc-block-forms'),
			getValue: ({ item }) => item?.responses ?? 0,
			render: ({ item }) => {
				const count = item?.responses ?? 0;
				return (
					<Button
						icon={inbox}
						text={count.toLocaleString()}
						size="compact"
						variant="tertiary"
						label={sprintf(
							/* translators: %d: number of responses */
							__('Review %d responses', 'prc-block-forms'),
							count
						)}
						disabled={!count}
						onClick={(event) => {
							event.stopPropagation();
							if (!count || !onOpenResponses) {
								return;
							}
							onOpenResponses(item);
						}}
					/>
				);
			},
			enableSorting: false,
		},
		{
			id: 'unread',
			label: __('Unread', 'prc-block-forms'),
			getValue: ({ item }) => item?.unread ?? 0,
			render: ({ item }) => (
				<span
					className={
						item?.unread
							? 'prc-forms-library__unread is-unread'
							: 'prc-forms-library__unread'
					}
				>
					{(item?.unread ?? 0).toLocaleString()}
				</span>
			),
			enableSorting: false,
		},
		{
			id: 'method',
			label: __('Method', 'prc-block-forms'),
			getValue: ({ item }) => item?.method || '',
			render: ({ item }) => (
				<span>{getOptionLabel('methods', item?.method)}</span>
			),
			elements: getElements('methods'),
			filterBy: {
				operators: ['isAny'],
			},
			enableSorting: false,
		},
		{
			id: 'fieldCount',
			label: __('Fields', 'prc-block-forms'),
			getValue: ({ item }) => item?.field_count ?? 0,
			render: ({ item }) => <span>{item?.field_count ?? 0}</span>,
			enableSorting: true,
		},
	];

	if (!canViewResponses) {
		return fields.filter(
			(field) => field.id !== 'responses' && field.id !== 'unread'
		);
	}

	return fields;
}
