/**
 * WordPress Dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	__experimentalText as Text,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { dispatch, useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { __, sprintf } from '@wordpress/i18n';
import {
	seen,
	trash,
	download,
	notAllowed,
	inbox,
	drafts,
} from '@wordpress/icons';
import { useState } from '@wordpress/element';

/**
 * Internal Dependencies
 */
import { exportResponsesToCsv } from './utils';

function formatFieldValue(field) {
	if (field.type === 'checkbox' || field.type === 'radio') {
		return `${field.checked ? __('Yes', 'prc-block-forms') : __('No', 'prc-block-forms')}${
			field.value ? ` (${field.value})` : ''
		}`;
	}
	return field.value || '—';
}

function ViewResponseModal({ items }) {
	const [item] = items;
	const metaRows = [
		[__('Date', 'prc-block-forms'), item.date],
		[__('Form', 'prc-block-forms'), item.formTitle || item.formName || '—'],
		[__('Action', 'prc-block-forms'), item.action],
		[__('Status', 'prc-block-forms'), item.status],
		[__('Source', 'prc-block-forms'), item.sourceUrl || '—'],
		[__('User Agent', 'prc-block-forms'), item.userAgent || '—'],
		[
			__('Folder', 'prc-block-forms'),
			item.isSpam
				? __('Spam', 'prc-block-forms')
				: __('Inbox', 'prc-block-forms'),
		],
		[
			__('Read state', 'prc-block-forms'),
			item.isUnread
				? __('Unread', 'prc-block-forms')
				: __('Read', 'prc-block-forms'),
		],
	];

	return (
		<VStack spacing={4}>
			<table className="prc-form-responses__detail-table">
				<tbody>
					{(item.fields || []).map((field, index) => (
						<tr key={`${field.id || field.name}-${index}`}>
							<th scope="row">
								{field.label || field.name || field.type}
							</th>
							<td>{formatFieldValue(field)}</td>
						</tr>
					))}
				</tbody>
			</table>
			<VStack spacing={1}>
				{metaRows.map(([label, value]) => (
					<Text key={label} variant="muted">
						<strong>{label}:</strong> {value}
					</Text>
				))}
			</VStack>
		</VStack>
	);
}

function DeleteResponseModal({ items, closeModal, onActionPerformed }) {
	const { createErrorNotice, createSuccessNotice } =
		useDispatch(noticesStore);
	const [isDeleting, setIsDeleting] = useState(false);
	const count = items.length;

	const handleConfirm = async () => {
		setIsDeleting(true);
		try {
			await apiFetch({
				path: '/prc-api/v3/form/responses/bulk-delete',
				method: 'POST',
				data: { ids: items.map((item) => item.id) },
			});
			createSuccessNotice(
				sprintf(
					/* translators: %d: number of deleted responses */
					__('%d response(s) deleted.', 'prc-block-forms'),
					count
				),
				{ type: 'snackbar' }
			);
			onActionPerformed?.(items);
			closeModal?.();
		} catch {
			createErrorNotice(
				__(
					'Could not delete the selected response(s).',
					'prc-block-forms'
				),
				{ type: 'snackbar' }
			);
		} finally {
			setIsDeleting(false);
		}
	};

	return (
		<VStack spacing={3}>
			<Text>
				{count === 1
					? __(
							'Are you sure you want to permanently delete this response?',
							'prc-block-forms'
						)
					: sprintf(
							/* translators: %d: number of responses */
							__(
								'Are you sure you want to permanently delete these %d responses?',
								'prc-block-forms'
							),
							count
						)}
			</Text>
			<VStack spacing={2} direction="row" justify="flex-end">
				<Button
					variant="tertiary"
					onClick={closeModal}
					disabled={isDeleting}
				>
					{__('Cancel', 'prc-block-forms')}
				</Button>
				<Button
					variant="primary"
					isDestructive
					onClick={handleConfirm}
					isBusy={isDeleting}
					disabled={isDeleting}
				>
					{__('Delete', 'prc-block-forms')}
				</Button>
			</VStack>
		</VStack>
	);
}

async function setSpam(items, isSpam, refresh) {
	const { createErrorNotice, createSuccessNotice } = dispatch(noticesStore);
	try {
		await apiFetch({
			path: '/prc-api/v3/form/responses/spam',
			method: 'POST',
			data: {
				ids: items.map((item) => item.id),
				is_spam: isSpam,
			},
		});
		createSuccessNotice(
			isSpam
				? sprintf(
						/* translators: %d: number of responses */
						__('%d response(s) marked as spam.', 'prc-block-forms'),
						items.length
					)
				: sprintf(
						/* translators: %d: number of responses */
						__(
							'%d response(s) moved back to the inbox.',
							'prc-block-forms'
						),
						items.length
					),
			{ type: 'snackbar' }
		);
	} catch {
		createErrorNotice(
			__('Could not update the selected response(s).', 'prc-block-forms'),
			{ type: 'snackbar' }
		);
	}
	refresh();
}

async function setUnread(items, isUnread, refresh) {
	const { createErrorNotice, createSuccessNotice } = dispatch(noticesStore);
	try {
		await apiFetch({
			path: '/prc-api/v3/form/responses/unread',
			method: 'POST',
			data: {
				ids: items.map((item) => item.id),
				is_unread: isUnread,
			},
		});
		createSuccessNotice(
			isUnread
				? sprintf(
						/* translators: %d: number of responses */
						__(
							'%d response(s) marked as unread.',
							'prc-block-forms'
						),
						items.length
					)
				: sprintf(
						/* translators: %d: number of responses */
						__('%d response(s) marked as read.', 'prc-block-forms'),
						items.length
					),
			{ type: 'snackbar' }
		);
	} catch {
		createErrorNotice(
			__('Could not update the selected response(s).', 'prc-block-forms'),
			{ type: 'snackbar' }
		);
	}
	refresh();
}

export default function getActions(refresh) {
	return [
		{
			id: 'view-response',
			label: __('View', 'prc-block-forms'),
			icon: seen,
			isPrimary: true,
			isEligible: (item) => !!item?.id,
			RenderModal: ViewResponseModal,
			modalHeader: __('Form Response', 'prc-block-forms'),
		},
		{
			id: 'export-csv',
			label: __('Export CSV', 'prc-block-forms'),
			icon: download,
			supportsBulk: true,
			isEligible: (item) => !!item?.id,
			callback: (items) => {
				exportResponsesToCsv(items);
			},
		},
		{
			id: 'mark-read',
			label: __('Mark as read', 'prc-block-forms'),
			icon: drafts,
			supportsBulk: true,
			isEligible: (item) => !!item?.id && !!item.isUnread,
			callback: (items) => setUnread(items, false, refresh),
		},
		{
			id: 'mark-unread',
			label: __('Mark as unread', 'prc-block-forms'),
			icon: seen,
			supportsBulk: true,
			isEligible: (item) => !!item?.id && !item.isUnread,
			callback: (items) => setUnread(items, true, refresh),
		},
		{
			id: 'mark-spam',
			label: __('Mark as spam', 'prc-block-forms'),
			icon: notAllowed,
			supportsBulk: true,
			isEligible: (item) => !!item?.id && !item.isSpam,
			callback: (items) => setSpam(items, true, refresh),
		},
		{
			id: 'not-spam',
			label: __('Not spam', 'prc-block-forms'),
			icon: inbox,
			supportsBulk: true,
			isEligible: (item) => !!item?.id && !!item.isSpam,
			callback: (items) => setSpam(items, false, refresh),
		},
		{
			id: 'delete-response',
			label: __('Delete', 'prc-block-forms'),
			icon: trash,
			isDestructive: true,
			supportsBulk: true,
			isEligible: (item) => !!item?.id,
			RenderModal: (props) => (
				<DeleteResponseModal
					{...props}
					onActionPerformed={(items) => {
						props.onActionPerformed?.(items);
						refresh();
					}}
				/>
			),
			modalHeader: __('Delete Response', 'prc-block-forms'),
		},
	];
}
