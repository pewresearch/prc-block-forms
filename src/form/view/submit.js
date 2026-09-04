import { getContext, getElement, store } from '@wordpress/interactivity';

import { FormResponse } from './form-response';
import { clearFormData } from './persistence';

function pushFormError(context, error) {
	context.errors.push({
		id: `error-${Math.random().toString(36).substring(2, 15)}`,
		message: error?.message || 'An error occurred.',
		actionUrl: error?.actionUrl || null,
	});
}

function dispatchSubmitted(formEl, success, error) {
	formEl?.dispatchEvent(
		new CustomEvent('prc-form/submitted', {
			bubbles: true,
			detail: { success, error },
		})
	);
}

function finishSubmission(formEl, context, state) {
	context.submissionProcessing = false;
	context._isSubmitting = false;
	dispatchSubmitted(formEl, !!state.success, !!state.error);
}

/**
 * Continue form submit after captcha (or when the form has no captcha).
 */
export function* sendSubmission() {
	const context = getContext();
	const { captchaPassed, submissionProcessing, stopProcessing } = context;

	if (
		!captchaPassed ||
		stopProcessing ||
		false === submissionProcessing ||
		true === submissionProcessing ||
		context._isSubmitting
	) {
		return;
	}

	const {
		nonceName,
		nonceToken,
		submitMethod,
		formId,
		formPostId,
		formName,
	} = context;
	const { state, actions } = store('prc-block/form');
	const { ref: formEl } = getElement();

	context._isSubmitting = true;
	context.captchaHidden = true;
	context.submissionProcessing = true;

	const { method, action, namespace } = submitMethod;
	const { fieldsForSubmission } = state;
	const fieldsForSubmissionWithCaptcha = [
		...fieldsForSubmission,
		{
			id: 'nonce',
			name: nonceName,
			type: 'nonceToken',
			value: nonceToken,
		},
	];

	if (method === 'rest') {
		const slugifiedAction = action.replace(/([A-Z])/g, '-$1').toLowerCase();
		try {
			const response = yield window.wp.apiFetch({
				path: `/prc-api/v3/form/${slugifiedAction}?nonce=${nonceToken}`,
				method: 'POST',
				data: {
					formName,
					formId,
					formPostId,
					actionConfig: context.actionConfig || {},
					formFields: fieldsForSubmissionWithCaptcha,
				},
			});
			const formResponse = new FormResponse(response);
			if (formResponse.isSuccess) {
				actions.applyResponseDataToFields(formResponse.data);
				state.success = true;
				context.formMessage =
					formResponse?.message || 'Form submitted successfully.';
			} else {
				state.error = true;
			}
		} catch (error) {
			state.error = true;
			pushFormError(context, error);
		}
		finishSubmission(formEl, context, state);
	} else if (method === 'api') {
		const actionStore = yield store(namespace);
		if (actionStore?.actions[action]) {
			try {
				const result = yield actionStore.actions[action](
					fieldsForSubmissionWithCaptcha
				);
				const formResponse = new FormResponse(result);
				if (formResponse.isSuccess) {
					clearFormData(formId);
					actions.applyResponseDataToFields(formResponse.data);
					state.success = true;
					const redirectUrl = context.actionConfig?.redirectUrl;
					if (redirectUrl) {
						window.location.href = redirectUrl;
					} else {
						context.formMessage =
							formResponse?.message ||
							'Form submitted successfully.';
					}
					if (formResponse.actionUrl) {
						setTimeout(() => {
							window.location.href = formResponse.actionUrl;
						}, 1000);
					}
				} else {
					state.error = true;
					pushFormError(context, formResponse);
				}
			} catch (error) {
				state.error = true;
				pushFormError(context, error);
			} finally {
				finishSubmission(formEl, context, state);
			}
		} else {
			state.error = true;
			finishSubmission(formEl, context, state);
		}
	}
	clearFormData(formId);
}
