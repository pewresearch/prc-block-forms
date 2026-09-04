/**
 * WordPress Dependencies
 */
import {
	store,
	getContext,
	getElement,
	withSyncEvent,
	withScope,
} from '@wordpress/interactivity';

import subscribe from './subscribe';
import { FormPersistence } from './persistence';
import { sendSubmission } from './submit';

const collectFormFields = (ref) => {
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

const { state, actions } = store('prc-block/form', {
	state: {
		success: false,
		error: false,
		processing: false,
		get submissionDisabled() {
			const context = getContext();
			return !context.allowSubmit;
		},
		get fieldsForSubmission() {
			const context = getContext();
			const { formFields } = context;
			const allFormFields = state.formFields;
			// Find the formFields in allFormFields that are in formFields
			const formFieldsToSubmit = allFormFields.filter((field) =>
				formFields.includes(field.id)
			);
			// Add the captchaToken as a pseudo-field.
			formFieldsToSubmit.push({
				id: 'captchaToken',
				type: 'captchaToken',
				value: context.captchaToken,
				required: false,
				name: 'captchaToken',
			});

			return formFieldsToSubmit;
		},
		get inputType() {
			const { attributes } = getElement();
			const { id } = attributes;
			const { formFields } = state;
			return formFields.find((field) => field.id === id)?.type;
		},
		get inputValue() {
			const { attributes } = getElement();
			const { id } = attributes;
			const { formFields } = state;
			return formFields.find((field) => field.id === id)?.value;
		},
		get formattedInputValue() {
			const { attributes } = getElement();
			const { id } = attributes;
			const { formFields } = state;
			const field = formFields.find((formField) => formField.id === id);
			if (!field) return '';

			const value = field.value;
			const format = field.outputFormat;

			if (value === null || value === undefined) {
				return '';
			}

			switch (format) {
				case 'currency':
					return new Intl.NumberFormat('en-US', {
						style: 'currency',
						currency: 'USD',
					}).format(value);
				case 'percentage':
					return `${value}%`;
				case 'number':
				default:
					return value.toString();
			}
		},
		get inputName() {
			const { attributes } = getElement();
			const { id } = attributes;
			const { formFields } = state;
			return formFields.find((field) => field.id === id)?.name;
		},
		get inputPlaceholder() {
			const { attributes } = getElement();
			const { id } = attributes;
			const { formFields } = state;
			return formFields.find((field) => field.id === id)?.placeholder;
		},
		get isInputHidden() {
			const { attributes } = getElement();
			const { id } = attributes;
			const { formFields } = state;
			return formFields.find((field) => field.id === id)?.hidden;
		},
		get isInputDisabled() {
			const { attributes } = getElement();
			const { id } = attributes;
			const { formFields } = state;
			return formFields.find((field) => field.id === id)?.disabled;
		},
		get isInputReadOnly() {
			const { attributes } = getElement();
			const { id } = attributes;
			const { formFields } = state;
			return formFields.find((field) => field.id === id)?.readOnly;
		},
		get isInputCopied() {
			const { attributes } = getElement();
			const { id } = attributes;
			const { formFields } = state;
			return formFields.find((field) => field.id === id)?.copied || false;
		},
		get isInputRequired() {
			const { attributes } = getElement();
			const { id } = attributes;
			const { formFields } = state;
			return formFields.find((field) => field.id === id)?.required;
		},
		get isInputChecked() {
			const { attributes } = getElement();
			const { id } = attributes;
			const { formFields } = state;
			return formFields.find((field) => field.id === id)?.checked;
		},
		get isInputError() {
			// Check if this field has an error state.
			const { formFields } = state;
			const { attributes } = getElement();
			const { id } = attributes;
			return (
				formFields.find((field) => field.id === id)?.error ||
				state.error
			);
		},
		get isInputSuccess() {
			return state.success;
		},
		get isInputProcessing() {
			return state.processing;
		},
		get hasErrors() {
			const context = getContext();
			return context.errors.length > 0;
		},
		get formMessage() {
			const context = getContext();
			return context?.formMessage || false;
		},
		get isPageHidden() {
			const context = getContext();
			const { pageId } = context;
			if (!pageId) {
				return true;
			}
			return context.activePage !== pageId;
		},
		get submitButtonText() {
			const context = getContext();
			const { formPages, activePage } = context;

			// If currently processing, always show "Processing..."
			if (context.submissionProcessing) {
				return 'Processing...';
			}

			// If form has pages
			if (formPages) {
				const currentPageIndex = formPages.findIndex(
					(pageId) => pageId === activePage
				);
				const isLastPage = currentPageIndex === formPages.length - 1;
				return isLastPage ? 'Submit' : 'Next';
			}
			return context.submitButtonText || 'Submit';
		},
		get formDisplayCondition() {
			const context = getContext();
			const { formDisplayCondition } = context;
			const { name, operator, value } = formDisplayCondition || {};
			// Check if the condition is met
			const { formFields } = state;
			const targetField = formFields.find((field) => field.name === name);
			if (!targetField) {
				return false;
			}
			switch (operator) {
				case 'equals':
					return targetField.value === value;
				case 'not_equals':
					return targetField.value !== value;
				default:
					return false;
			}
		},
	},
	actions: {
		/**
		 * Update the state of an input field.
		 *
		 * @param {string} id    The id of the input field.
		 * @param {string} prop  The property to update.
		 * @param {any}    value The value to update the property to.
		 */
		updateInputStateProp: (id, prop, value) => {
			const { formFields } = state;
			const formField = formFields.find((field) => field.id === id);
			if (formField) {
				formField[prop] = value;
			}
			const { formId } = getContext();
			state.formFields = formFields;
			FormPersistence.saveFormData(formId, formFields);
		},
		applyResponseDataToFields: (data) => {
			if (!data || typeof data !== 'object') {
				return;
			}
			state.formFields = state.formFields.map((field) => {
				if (!field.responseKey || !(field.responseKey in data)) {
					return field;
				}
				return {
					...field,
					value: data[field.responseKey],
				};
			});
		},
		onLabelClick: withSyncEvent((event) => {
			// Find the adjacent input element and focus it.
			const input = event.target.nextElementSibling;
			if (
				input &&
				['INPUT', 'SELECT', 'TEXTAREA'].includes(input.tagName)
			) {
				input.focus();
			}
		}),
		/**
		 * Get a property of an input field by id.
		 *
		 * @param {string} id   The id of the input field.
		 * @param {string} prop The property to get.
		 * @return {any} The value of the property.
		 */
		getInputStateProp: (id, prop) => {
			const { formFields } = state;
			return formFields.find((field) => field.id === id)?.[prop];
		},
		onInputChange: withSyncEvent((event) => {
			const { value, id } = event.target;
			actions.updateInputStateProp(id, 'value', value);
			// Clear error and timer on user input
			actions.updateInputStateProp(id, 'error', false);
		}),
		onInputRangeChange: withSyncEvent((event) => {
			const { value, id } = event.target;
			// Convert string to number for range inputs
			actions.updateInputStateProp(id, 'value', parseFloat(value));
			// Clear error on user input
			actions.updateInputStateProp(id, 'error', false);
		}),
		onInputCheckboxClick: withSyncEvent((event) => {
			const { checked, id } = event.target;
			actions.updateInputStateProp(id, 'checked', checked);
			// Clear error and timer on user input
			actions.updateInputStateProp(id, 'error', false);
		}),
		onInputFocus: withSyncEvent((event) => {
			const { id } = event.target;
			// Clear error and timer on focus
			actions.updateInputStateProp(id, 'error', false);
		}),
		onInputBlur: withSyncEvent(() => {}),
		onInputMouseEnter: withSyncEvent(() => {}),
		onInputMouseLeave: withSyncEvent(() => {}),
		onCopyToClipboard: withSyncEvent(async (event) => {
			const { id, value } = event.target;
			if (!value) {
				return;
			}
			event.target.select();
			try {
				await window.navigator.clipboard.writeText(value);
				actions.updateInputStateProp(id, 'copied', true);
				setTimeout(
					withScope(() => {
						actions.updateInputStateProp(id, 'copied', false);
					}),
					1500
				);
			} catch {
				// clipboard can be blocked without a secure context
			}
		}),
		checkForRequiredFieldsWithoutValues: () => {
			const context = getContext();
			const { fieldsForSubmission } = state;
			// Check if any of the formFieldsToSubmit are required and if they don't have a value, then set the error on their state to be true.
			let stopProcessing = false;
			fieldsForSubmission.forEach((field) => {
				if (
					field.required &&
					(field.type === 'checkbox' || field.type === 'radio'
						? !field.checked
						: !field.value)
				) {
					actions.updateInputStateProp(field.id, 'error', true);
					stopProcessing = true;
				} else {
					actions.updateInputStateProp(field.id, 'error', false);
				}
			});
			context.stopProcessing = stopProcessing;
		},
		/**
		 * This function runs when a form is submitted.
		 * It will check for required fields that are missing values,
		 * set the submission processing to true,
		 * show the captcha,
		 * and then hand off to the Captcha block to allow the submission to continue.
		 * Below, you'll find the sendSubmission callback that watches for the captchaPassed state change and then continues with the form specific submission logic.
		 */
		onSubmit: withSyncEvent(async (event) => {
			event.preventDefault();
			const context = getContext();
			const { formPages, activePage } = context;
			if (formPages && formPages.length > 0) {
				// If there are form pages, then we need to check if we're on the last page.
				const currentPageIndex = formPages.findIndex(
					(pageId) => pageId === activePage
				);
				if (currentPageIndex < formPages.length - 1) {
					// Not on the last page, so go to the next page.
					context.activePage = formPages[currentPageIndex + 1];
					getElement().ref?.dispatchEvent(
						new CustomEvent('prc-form/submitted', {
							bubbles: true,
							detail: { success: false, aborted: true },
						})
					);
					return;
				}
				// If we're here, then we're on the last page and can continue with submission.
			}

			if (context.submissionProcessing) {
				// Prevent double submission
				return;
			}

			// First, we do form validation.
			actions.checkForRequiredFieldsWithoutValues();

			if (context.stopProcessing) {
				getElement().ref?.dispatchEvent(
					new CustomEvent('prc-form/submitted', {
						bubbles: true,
						detail: { success: false },
					})
				);
				return;
			}

			context.submissionProcessing = null;

			const { ref: formEl } = getElement();
			const hasCaptcha = !!formEl?.querySelector(
				'.wp-block-prc-block-form-captcha'
			);
			if (hasCaptcha) {
				context.captchaHidden = false;
				return;
			}

			context.captchaHidden = true;
			context.captchaPassed = true;
		}),
		/**
		 * Resets the form submission process to initial state.
		 * This allows the user to submit the form again.
		 */
		onReset: () => {
			const context = getContext();
			context.submissionProcessing = false;
			context.captchaHidden = true;
			context.stopProcessing = false;
			context.allowSubmit = true;
			context.errors = [];
			context._isSubmitting = false; // Reset submission flag
			FormPersistence.clearFormData(context.formId);
		},
		/**
		 * Dismissing an error message resets the form submission process to initial state.
		 */
		onErrorClick: withSyncEvent((event) => {
			event.preventDefault();
			const { actionUrl } = event.target.dataset;
			if (actionUrl) {
				window.location.href = actionUrl;
			}
			actions.onReset();
		}),
		subscribe: async (fieldsForSubmission) => {
			const context = getContext();
			const actionConfig = context.actionConfig || {};
			const { audienceId, segmentId, interest } = actionConfig;
			const hasAudience =
				typeof audienceId === 'string' && audienceId.length > 0;
			const hasLegacyInterest =
				typeof interest === 'string' && interest.length > 0;

			if (!hasAudience && !hasLegacyInterest) {
				return Promise.reject({
					status: 'error',
					message: 'No Mailchimp audience or interest id provided',
				});
			}

			const emailAddress = fieldsForSubmission.find((field) =>
				['emailAddress', 'email'].includes(field.name)
			)?.value;
			const captchaToken = fieldsForSubmission.find(
				(field) => field.name === 'captchaToken'
			)?.value;

			return subscribe({
				emailAddress,
				audienceId: hasAudience ? audienceId : false,
				segmentId: hasAudience ? segmentId || false : false,
				interest: !hasAudience ? interest : false,
				captchaToken,
				formId: context.formName || false,
			})
				.then((response) => ({
					status: 'success',
					message: 'You have been subscribed to the list.',
					data: response,
				}))
				.catch((error) =>
					Promise.reject({
						status: 'error',
						message: error.message,
						data: error,
					})
				);
		},
		subscribeSelect: async (fieldsForSubmission) => {
			const context = getContext();
			const actionConfig = context.actionConfig || {};
			const { audienceId } = actionConfig;
			const hasAudience =
				typeof audienceId === 'string' && audienceId.length > 0;

			const emailAddress = fieldsForSubmission.find((field) =>
				['emailAddress', 'email'].includes(field.name)
			)?.value;
			const captchaToken = fieldsForSubmission.find(
				(field) => field.name === 'captchaToken'
			)?.value;
			const selectedValues = fieldsForSubmission
				.filter((field) => field.type === 'checkbox' && field.checked)
				.map((field) => field.value);

			return subscribe({
				emailAddress,
				audienceId: hasAudience ? audienceId : false,
				segmentIds: hasAudience ? selectedValues : false,
				interests: !hasAudience ? selectedValues : false,
				captchaToken,
				apiKey: 'mailchimp-select',
			})
				.then((response) => ({
					status: 'success',
					message: 'You have been subscribed to the selected lists.',
					data: response,
				}))
				.catch((error) =>
					Promise.reject({
						status: 'error',
						message: error.message,
						data: error,
					})
				);
		},
	},
	callbacks: {
		onFormMount: () => {
			const { ref, attributes } = getElement();
			let { id } = attributes;
			if (!id) {
				id = `prc-block-form-${Math.random().toString(36).substring(2, 15)}`;
			}
			const formFields = collectFormFields(ref);
			const context = getContext();
			context.formId = id;
			context.formFields = formFields;
			// Clear any expired form data from localStorage
			FormPersistence.clearExpiredData();
			// Load saved form data if it exists
			const savedFormFields = FormPersistence.loadFormData(id);
			if (savedFormFields) {
				// go through state.formFields and find the ones that are in savedFormFields and update the state with the saved values
				state.formFields = state.formFields.map((field) => {
					// If the field id is in savedFormFields then update the value with the saved value
					const savedField = savedFormFields.find(
						(saved) => saved.id === field.id
					);
					if (savedField) {
						field.value = savedField?.value;
						field.checked = savedField?.checked;
					}
					return field;
				});
			}
		},
		onCaptchaPassing: () => {
			const context = getContext();
			if (context.captchaPassed) {
				context.captchaHidden = true;
			}
		},
		sendSubmission,
	},
});
