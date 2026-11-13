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

function newSubmissionId() {
	const { crypto } = window;
	if (typeof crypto?.randomUUID === 'function') {
		return crypto.randomUUID();
	}
	// crypto.randomUUID() needs a secure context. Build a version 4 UUID from random bytes.
	const bytes = crypto.getRandomValues(new Uint8Array(16));
	const hex = Array.from(bytes, (byte) =>
		byte.toString(16).padStart(2, '0')
	).join('');
	const variant = '89ab'[bytes[8] % 4];
	return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-4${hex.slice(13, 16)}-${variant}${hex.slice(17, 20)}-${hex.slice(20)}`;
}

/**
 * One entry, one submission ID. A retry of the same values sends the same ID,
 * so the server can tell a repeat from a new entry, and a press after a success
 * gets the stored answer instead of a second email. An edit starts a new entry.
 *
 * @param {Object} context Form context. Holds the ID and the values it was made for.
 * @param {Array}  fields  Fields of the entry on screen.
 * @return {string} The submission ID.
 */
function submissionIdFor(context, fields) {
	// A retry mounts a fresh captcha, so its token changes without an edit.
	const fingerprint = JSON.stringify(
		fields
			.filter(({ type }) => type !== 'captchaToken')
			.map(({ id, value, checked }) => [id, value, checked ?? null])
	);
	if (
		!context.submissionId ||
		context.submissionFingerprint !== fingerprint
	) {
		context.submissionId = newSubmissionId();
		context.submissionFingerprint = fingerprint;
	}
	return context.submissionId;
}

const STILL_SENDING_MESSAGE = 'Still sending your entry. Please wait.';
const RETRY_FIRST_DELAY_MS = 1000;
const RETRY_MAX_DELAY_MS = 8000;
const LEASE_SECONDS_FALLBACK = 30;
const LEASE_MARGIN_MS = 1000;

const waitFor = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function isStillSending(error) {
	return error?.code === 'submission_in_progress';
}

/**
 * Post one entry. A 409 means the server still holds this submission ID for a
 * request that has not ended, so the entry is not lost and not sent twice.
 * Keep the same ID and retry with a growing delay until the server lease ends,
 * then give up with the normal error.
 *
 * The status message is stored under the form's phase key, so only this form's
 * own status region shows it.
 *
 * @param {Object}   context Form context.
 * @param {Object}   state   The prc-block/form state. Holds the status message of each form.
 * @param {Function} send    Posts the entry and returns a promise.
 * @return {Object} A generator that yields the posts and ends with the server answer.
 */
function* postUntilNotInProgress(context, state, send) {
	let delay = RETRY_FIRST_DELAY_MS;
	let deadline = null;
	try {
		while (true) {
			try {
				return yield send();
			} catch (error) {
				if (!isStillSending(error)) {
					throw error;
				}
				const leaseSeconds =
					Number(error?.data?.leaseSeconds) || LEASE_SECONDS_FALLBACK;
				if (deadline === null) {
					deadline =
						Date.now() + leaseSeconds * 1000 + LEASE_MARGIN_MS;
				}
				const remaining = deadline - Date.now();
				if (remaining <= 0) {
					throw error;
				}
				state.formStatusMessages[formPhaseKey(context)] =
					STILL_SENDING_MESSAGE;
				yield waitFor(Math.min(delay, remaining));
				delay = Math.min(delay * 2, RETRY_MAX_DELAY_MS);
			}
		}
	} finally {
		state.formStatusMessages[formPhaseKey(context)] = '';
	}
}

function dispatchSubmitted(formEl, success, error) {
	formEl?.dispatchEvent(
		new CustomEvent('prc-form/submitted', {
			bubbles: true,
			detail: { success, error },
		})
	);
}

/**
 * The values of this form's fields, as one string, so an edit after an
 * answer can be told apart from a keyup that changed nothing.
 *
 * @param {Object} state   The prc-block/form state.
 * @param {Object} context The form's context.
 * @return {string} The key.
 */
export function formValuesKey(state, context) {
	const ids = context.formFields || [];
	return JSON.stringify(
		state.formFields
			.filter((field) => ids.includes(field.id))
			.map((field) => [field.id, field.value, field.checked])
	);
}

/**
 * The key of a form's phase in state.formPhases. HTML cached before the
 * instance ID shipped has none, so those forms fall back to the form ID.
 *
 * @param {Object} context The form's context.
 * @return {string} The key.
 */
export function formPhaseKey(context) {
	return context.instanceId ?? context.formId;
}

function settle(context, state, phase) {
	context._answeredValues = formValuesKey(state, context);
	state.formPhases[formPhaseKey(context)] = phase;
}

function isFocusable(el) {
	return !!el && !el.disabled && el.getClientRects().length > 0;
}

/**
 * Whether a script may move focus now. Focus must be inside the form, or on
 * nothing, so a late answer never takes it from a field the reader moved to
 * in another form.
 *
 * @param {HTMLElement} formEl The form that wants to move focus.
 * @return {boolean} True when the move is safe.
 */
function mayMoveFocus(formEl) {
	const { activeElement, body, documentElement } = formEl.ownerDocument;
	return (
		!activeElement ||
		activeElement === body ||
		activeElement === documentElement ||
		formEl.contains(activeElement)
	);
}

/**
 * Moves focus to the first usable target once the form has rendered its new
 * phase, and only inside the form that asked for it.
 *
 * @param {HTMLElement} formEl    The form.
 * @param {string[]}    selectors Target selectors, best first.
 */
function focusInForm(formEl, selectors) {
	window.requestAnimationFrame(() => {
		if (!formEl || !mayMoveFocus(formEl)) {
			return;
		}
		for (const selector of selectors) {
			const target = Array.from(formEl.querySelectorAll(selector)).find(
				isFocusable
			);
			if (!target) {
				continue;
			}
			if (
				!target.matches('button, input, select, textarea, [tabindex]')
			) {
				target.setAttribute('tabindex', '-1');
			}
			target.focus();
			return;
		}
	});
}

/**
 * Moves focus to the success message or the first error once it renders,
 * since the focused Submit button can be hidden or dimmed by then.
 *
 * @param {HTMLElement} formEl The form.
 * @param {string}      phase  The phase the answer set.
 */
function focusStatus(formEl, phase) {
	focusInForm(
		formEl,
		'success' === phase
			? ['.wp-block-prc-block-form-message']
			: ['.wp-block-prc-block-form-error button']
	);
}

/**
 * Returns focus to the form after an error is dismissed. The error button
 * that held focus is gone by then, so focus goes to the Submit button, or to
 * the first field when the Submit button is on another page of the form.
 *
 * @param {HTMLElement} formEl The form.
 */
export function focusAfterDismiss(formEl) {
	focusInForm(formEl, [
		'button[type="submit"]',
		'input:not([type="hidden"]), select, textarea',
	]);
}

function finishSubmission(formEl, context, state) {
	context.submissionProcessing = false;
	context._isSubmitting = false;
	const phase = state.formPhases[formPhaseKey(context)];
	dispatchSubmitted(formEl, 'success' === phase, 'error' === phase);
	focusStatus(formEl, phase);
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
		const submissionId = submissionIdFor(context, fieldsForSubmission);
		try {
			const response = yield* postUntilNotInProgress(context, state, () =>
				window.wp.apiFetch({
					path: `/prc-api/v3/form/${slugifiedAction}?nonce=${nonceToken}`,
					method: 'POST',
					data: {
						formName,
						formId,
						formPostId,
						submissionId,
						actionConfig: context.actionConfig || {},
						formFields: fieldsForSubmissionWithCaptcha,
					},
				})
			);
			const formResponse = new FormResponse(response);
			if (formResponse.isSuccess) {
				clearFormData(formId);
				actions.applyResponseDataToFields(formResponse.data);
				settle(context, state, 'success');
				context.formMessage =
					formResponse?.message || 'Form submitted successfully.';
			} else {
				settle(context, state, 'error');
			}
		} catch (error) {
			settle(context, state, 'error');
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
					settle(context, state, 'success');
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
					settle(context, state, 'error');
					pushFormError(context, formResponse);
				}
			} catch (error) {
				settle(context, state, 'error');
				pushFormError(context, error);
			} finally {
				finishSubmission(formEl, context, state);
			}
		} else {
			settle(context, state, 'error');
			finishSubmission(formEl, context, state);
		}
	}
}
