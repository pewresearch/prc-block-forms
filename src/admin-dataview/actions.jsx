import { __ } from '@wordpress/i18n';
import { inbox } from '@wordpress/icons';

export default function getFormActions(
	actions,
	{ onOpenResponses, canViewResponses = false } = {}
) {
	const mapped = actions.map((action) => {
		if ('edit' === action.id) {
			return {
				...action,
				label: __('Edit Form', 'prc-block-forms'),
			};
		}

		return action;
	});

	if (!canViewResponses) {
		return mapped;
	}

	const editIndex = mapped.findIndex((action) => action.id === 'edit');
	const insertAt = editIndex >= 0 ? editIndex + 1 : mapped.length;

	const viewResponses = {
		id: 'view-responses',
		label: __('View Responses', 'prc-block-forms'),
		icon: inbox,
		isEligible: (item) => !!item?.responses,
		callback: ([item]) => {
			onOpenResponses?.(item);
		},
	};

	return [
		...mapped.slice(0, insertAt),
		viewResponses,
		...mapped.slice(insertAt),
	];
}
