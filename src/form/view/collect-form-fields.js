/**
 * The IDs of the inputs inside a form element.
 *
 * @param {HTMLElement} ref The form element.
 * @return {string[]} The input IDs.
 */
export const collectFormFields = (ref) => {
	// find all the input elements in the ref that have a class name that contains at least 'wp-block-prc-block-form-input-*'
	const inputElements = ref.querySelectorAll(
		'input, select, textarea, .wp-block-prc-block-form-input-password, .wp-block-prc-block-form-input-radio-group'
	);
	// Find all that have a name attribute and return an array of objects with the name, the value, and the ref
	const formFields = [];
	inputElements.forEach((input) => {
		if (input.type === 'password') {
			return;
		}
		if (input.id) {
			formFields.push(input.id);
		}
	});
	return formFields;
};
