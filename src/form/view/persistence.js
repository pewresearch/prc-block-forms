const STORAGE_KEY_PREFIX = 'prc_form_data_';
const EXPIRY_HOURS = 24;

/**
 * Storage key for one form.
 *
 * @param {string} formId Form id.
 * @return {string} localStorage key.
 */
function getStorageKey(formId) {
	return `${STORAGE_KEY_PREFIX}${formId}`;
}

/**
 * Save non-sensitive field values for a form.
 *
 * @param {string} formId     Form id.
 * @param {Array}  formFields Current field state.
 */
export function saveFormData(formId, formFields) {
	try {
		const dataToSave = {
			timestamp: Date.now(),
			fields: formFields
				.filter((field) => {
					const sensitiveTypes = ['password', 'hidden'];
					return field.value && !sensitiveTypes.includes(field.type);
				})
				.map((field) => ({
					id: field.id,
					name: field.name,
					value: field.value,
					checked: field?.checked || null,
				})),
		};

		window.localStorage.setItem(
			getStorageKey(formId),
			JSON.stringify(dataToSave)
		);
	} catch {
		// private mode and quota errors throw here
	}
}

/**
 * Load saved field values for a form.
 *
 * @param {string} formId Form id.
 * @return {Array|null} Saved fields, or null when missing or expired.
 */
export function loadFormData(formId) {
	try {
		const stored = window.localStorage.getItem(getStorageKey(formId));
		if (!stored) {
			return null;
		}

		const data = JSON.parse(stored);
		const expiryTime = data.timestamp + EXPIRY_HOURS * 60 * 60 * 1000;

		if (Date.now() > expiryTime) {
			clearFormData(formId);
			return null;
		}

		return data.fields;
	} catch {
		return null;
	}
}

/**
 * Remove saved field values for a form.
 *
 * @param {string} formId Form id.
 */
export function clearFormData(formId) {
	try {
		window.localStorage.removeItem(getStorageKey(formId));
	} catch {
		// private mode and quota errors throw here
	}
}

/**
 * Drop expired form snapshots from localStorage.
 */
export function clearExpiredData() {
	try {
		const now = Date.now();
		const keysToRemove = [];

		for (let i = 0; i < window.localStorage.length; i++) {
			const key = window.localStorage.key(i);
			if (key && key.startsWith(STORAGE_KEY_PREFIX)) {
				try {
					const data = JSON.parse(window.localStorage.getItem(key));
					const expiryTime =
						data.timestamp + EXPIRY_HOURS * 60 * 60 * 1000;
					if (now > expiryTime) {
						keysToRemove.push(key);
					}
				} catch {
					keysToRemove.push(key);
				}
			}
		}

		keysToRemove.forEach((key) => window.localStorage.removeItem(key));
	} catch {
		// private mode and quota errors throw here
	}
}

export const FormPersistence = {
	saveFormData,
	loadFormData,
	clearFormData,
	clearExpiredData,
};
