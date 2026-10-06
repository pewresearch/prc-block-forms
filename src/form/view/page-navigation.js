/**
 * Registers the paginated-form navigation state and actions on the
 * `prc-block/form` Interactivity store.
 *
 * The parent form block puts every `prc-block/form-page` id in
 * `context.formPages` and the active one in `context.activePage`. Going back a
 * page and resetting the form are the two reader controls the form-submit block
 * renders under the Next button.
 */
import { store, getContext, withSyncEvent } from '@wordpress/interactivity';

/**
 * Internal Dependencies
 */
import { FormPersistence } from './persistence';

// Field values as the server rendered them, keyed by form id then field id.
// Reset restores these instead of blanking fields that shipped with a default.
const pristineFormFields = new Map();

// Radio options and selects keep their selection in their own stores.
const RADIO_GROUP_NAMESPACE = 'prc-block/form-input-radio-group';
const SELECT_NAMESPACE = 'prc-block/form-input-select';

/**
 * Remember the rendered value of every field in a form.
 *
 * Call this before saved progress is merged in, so Reset returns the reader to
 * the form as it was published rather than to empty fields.
 *
 * @param {string} formId     Form id.
 * @param {Array}  fieldIds   Ids of the fields that belong to this form.
 * @param {Array}  formFields All registered fields on the page.
 */
export function savePristineFormFields(formId, fieldIds, formFields) {
	const radioOptions = store(RADIO_GROUP_NAMESPACE).state.formFields || [];
	pristineFormFields.set(
		formId,
		Object.fromEntries(
			[...formFields, ...radioOptions]
				.filter((field) => fieldIds.includes(field.id))
				.map((field) => [
					field.id,
					{ value: field.value, checked: field.checked ?? false },
				])
		)
	);
}

function dispatchSubmitted(formEl, detail) {
	formEl?.dispatchEvent(
		new CustomEvent('prc-form/submitted', { bubbles: true, detail })
	);
}

/**
 * First control on a page that fails its constraints (required, type, pattern).
 *
 * Controls hidden by their own binding, such as a conditional field, are
 * skipped because the reader cannot reach them to fix them.
 *
 * @param {HTMLElement} formEl Form element.
 * @param {string}      pageId Page element id.
 * @return {HTMLElement|undefined} The invalid control, if any.
 */
function findInvalidControl(formEl, pageId) {
	const pageEl = formEl.querySelector(`#${window.CSS.escape(pageId)}`);
	const controls = [
		...(pageEl?.querySelectorAll('input, select, textarea') ?? []),
	];
	// An open select combobox shows its search text; validate the committed
	// selection instead.
	const selectState = store(SELECT_NAMESPACE).state;
	controls.forEach((control) => {
		if (control.id && selectState[control.id]) {
			selectState[control.id].isOpen = false;
			control.value = selectState[control.id].value ?? '';
		}
	});
	return controls.find((control) => {
		const hiddenAncestor = control.closest('[hidden]');
		const isReachable = !hiddenAncestor || hiddenAncestor === pageEl;
		return isReachable && !control.checkValidity();
	});
}

/**
 * Handle a submit event on a paginated form.
 *
 * Paginated forms opt out of native validation (`novalidate`): the browser
 * would validate controls on hidden pages, fail to focus them, and cancel the
 * submit. The active page is validated here instead, with the same native
 * messages. Pages before the last advance on success.
 *
 * @param {Object}      context Form interactivity context.
 * @param {HTMLElement} formEl  Form element.
 * @return {boolean} True when the event was consumed by pagination, false when
 *                   the reader is on the last page and every field on it is valid.
 */
export function stepPaginatedForm(context, formEl) {
	const { formPages, activePage } = context;
	if (!Array.isArray(formPages) || formPages.length === 0) {
		return false;
	}
	const invalidControl = findInvalidControl(formEl, activePage);
	if (invalidControl) {
		invalidControl.reportValidity();
		dispatchSubmitted(formEl, { success: false });
		return true;
	}
	const currentPageIndex = formPages.indexOf(activePage);
	if (currentPageIndex >= formPages.length - 1) {
		return false;
	}
	context.activePage = formPages[currentPageIndex + 1];
	dispatchSubmitted(formEl, { success: false, aborted: true });
	return true;
}

const { state, actions } = store('prc-block/form', {
	state: {
		get isPaginatedForm() {
			const { formPages } = getContext();
			return Array.isArray(formPages) && formPages.length > 1;
		},
		get activePageIndex() {
			const { formPages, activePage } = getContext();
			if (!Array.isArray(formPages)) {
				return -1;
			}
			return formPages.indexOf(activePage);
		},
		get isPageNavigationHidden() {
			return !state.isPaginatedForm;
		},
		get isPreviousPageHidden() {
			return !state.isPaginatedForm || state.activePageIndex < 1;
		},
	},
	actions: {
		/**
		 * Step a paginated form back one page.
		 */
		onPreviousPageClick: withSyncEvent((event) => {
			event.preventDefault();
			const context = getContext();
			const { formPages } = context;
			const currentPageIndex = state.activePageIndex;
			if (!Array.isArray(formPages) || currentPageIndex < 1) {
				return;
			}
			context.activePage = formPages[currentPageIndex - 1];
		}),
		/**
		 * Clear every field in this form and return to the first page.
		 */
		onResetFormClick: withSyncEvent((event) => {
			event.preventDefault();
			actions.resetForm();
		}),
		/**
		 * Restore this form's fields to their rendered values, drop the saved
		 * progress, and send the reader back to the first page.
		 */
		resetForm: () => {
			const context = getContext();
			const { formId, formFields, formPages } = context;
			const pristine = pristineFormFields.get(formId) || {};
			state.formFields = state.formFields.map((field) => {
				if (!formFields.includes(field.id)) {
					return field;
				}
				const initial = pristine[field.id] || {};
				return {
					...field,
					value: initial.value ?? '',
					checked: initial.checked ?? false,
					error: false,
				};
			});
			const radioState = store(RADIO_GROUP_NAMESPACE).state;
			if (Array.isArray(radioState.formFields)) {
				radioState.formFields = radioState.formFields.map((field) =>
					formFields.includes(field.id)
						? {
								...field,
								checked: pristine[field.id]?.checked ?? false,
							}
						: field
				);
			}
			const selectState = store(SELECT_NAMESPACE).state;
			formFields.forEach((id) => {
				if (selectState[id]) {
					selectState[id].value = pristine[id]?.value ?? '';
					selectState[id].isOpen = false;
				}
			});
			FormPersistence.clearFormData(formId);
			context.errors = [];
			context.stopProcessing = false;
			context.submissionProcessing = false;
			context.captchaHidden = true;
			context.captchaPassed = false;
			context.captchaToken = '';
			context.allowSubmit = true;
			if (Array.isArray(formPages) && formPages.length > 0) {
				context.activePage = formPages[0];
			}
		},
	},
});
