/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Escape a value for CSV output.
 *
 * @param {*} value The raw value.
 * @return {string} The escaped value.
 */
function escapeCsvValue(value) {
	const stringValue = String(value ?? '');
	if (/[",\n]/.test(stringValue)) {
		return `"${stringValue.replace(/"/g, '""')}"`;
	}
	return stringValue;
}

/**
 * Build and download a CSV of the given responses. Field columns are the
 * union of every field label/name across the selected responses, so mixed
 * forms export cleanly.
 *
 * @param {Array} items The response rows to export.
 */
export function exportResponsesToCsv(items) {
	if (!items.length) {
		return;
	}

	const metaColumns = ['id', 'date', 'form', 'action', 'status', 'source'];
	const fieldColumns = [];
	items.forEach((item) => {
		(item.fields || []).forEach((field) => {
			const key = field.label || field.name || field.id;
			if (key && !fieldColumns.includes(key)) {
				fieldColumns.push(key);
			}
		});
	});

	const rows = items.map((item) => {
		const fieldValues = {};
		(item.fields || []).forEach((field) => {
			const key = field.label || field.name || field.id;
			if (!key) {
				return;
			}
			fieldValues[key] =
				field.type === 'checkbox' || field.type === 'radio'
					? `${field.checked ? 'Yes' : 'No'}${field.value ? ` (${field.value})` : ''}`
					: field.value;
		});
		return [
			item.id,
			item.date,
			item.formTitle || item.formName || '',
			item.action,
			item.status,
			item.sourceUrl || '',
			...fieldColumns.map((key) => fieldValues[key] ?? ''),
		];
	});

	const csv = [
		[...metaColumns, ...fieldColumns].map(escapeCsvValue).join(','),
		...rows.map((row) => row.map(escapeCsvValue).join(',')),
	].join('\n');

	const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
	const url = URL.createObjectURL(blob);
	const link = document.createElement('a');
	link.href = url;
	link.download = `form-responses-${new Date().toISOString().slice(0, 10)}.csv`;
	document.body.appendChild(link);
	link.click();
	document.body.removeChild(link);
	URL.revokeObjectURL(url);
}

/**
 * Plain label and help text for a response status.
 *
 * `pending` is the one to watch. A row stays `pending` only when the request
 * stopped after the row was stored and before the email result was saved.
 *
 * @param {string} status The status value stored on the row.
 * @return {{label: string, help: string, needsAttention: boolean}} Display data.
 */
export function getStatusInfo(status) {
	switch (status) {
		case 'pending':
			return {
				label: __('Pending', 'prc-block-forms'),
				help: __(
					'The email step did not finish. The email may or may not have been sent. Check the mail log before you act on it.',
					'prc-block-forms'
				),
				needsAttention: true,
			};
		case 'sent':
			return {
				label: __('Sent', 'prc-block-forms'),
				help: __('The email was sent.', 'prc-block-forms'),
				needsAttention: false,
			};
		case 'send_failed':
			return {
				label: __('Send failed', 'prc-block-forms'),
				help: __(
					'The email could not be sent. Mark the response as spam and then as not spam to try again.',
					'prc-block-forms'
				),
				needsAttention: true,
			};
		case 'send_unknown':
			return {
				label: __('Send unknown', 'prc-block-forms'),
				help: __(
					'The mail service did not answer in time. The email may or may not have been delivered. It was not sent again.',
					'prc-block-forms'
				),
				needsAttention: true,
			};
		case 'spam':
			return {
				label: __('Spam (not emailed)', 'prc-block-forms'),
				help: __(
					'Stored as spam. No email was sent. "Not spam" sends it once.',
					'prc-block-forms'
				),
				needsAttention: false,
			};
		case 'received':
			return {
				label: __('Received', 'prc-block-forms'),
				help: __(
					'Stored. This action sends no email.',
					'prc-block-forms'
				),
				needsAttention: false,
			};
		default:
			return { label: status || '', help: '', needsAttention: false };
	}
}
