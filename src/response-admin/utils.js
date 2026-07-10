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
