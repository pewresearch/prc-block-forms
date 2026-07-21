/**
 * WordPress Dependencies
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

/**
 * Turnstile client error codes that will not recover via auto-retry.
 *
 * @see https://developers.cloudflare.com/turnstile/troubleshooting/client-side-errors/error-codes/
 */
const NON_RETRYABLE_TURNSTILE_CODES = new Set([
	110100, // Invalid sitekey
	110110, // Sitekey not found
	110200, // Domain not authorized
	200100, // Clock or cache problem
	400020, // Invalid sitekey
	400070, // Sitekey disabled
]);

/**
 * Surface a single captcha error on the parent form context.
 *
 * @param {Object} targetContext Parent form interactivity context.
 * @param {string} message       User-facing error message.
 */
function pushCaptchaError(targetContext, message) {
	if (!Array.isArray(targetContext.errors)) {
		targetContext.errors = [];
	}
	if (targetContext.errors.some((error) => error?.id === 'turnstile-error')) {
		return;
	}
	targetContext.errors.push({
		id: 'turnstile-error',
		message,
		actionUrl: null,
	});
}

/**
 * Clear a previously surfaced captcha error after a successful challenge.
 *
 * @param {Object} targetContext Parent form interactivity context.
 */
function clearCaptchaError(targetContext) {
	if (!Array.isArray(targetContext.errors)) {
		return;
	}
	targetContext.errors = targetContext.errors.filter(
		(error) => error?.id !== 'turnstile-error'
	);
}

/**
 * Whether a Turnstile client error will not recover via auto-retry.
 *
 * @see https://developers.cloudflare.com/turnstile/troubleshooting/client-side-errors/error-codes/
 *
 * @param {number|string} errorCode Turnstile error code.
 * @return {boolean} True when the error should be surfaced immediately.
 */
function isNonRetryableTurnstileError(errorCode) {
	const code = Number(errorCode);
	if (!Number.isFinite(code)) {
		return false;
	}
	if (NON_RETRYABLE_TURNSTILE_CODES.has(code)) {
		return true;
	}
	const family = Math.floor(code / 1000);
	// 100- and 400-family are config/sitekey failures that won't auto-recover.
	// 110-family is mixed: only the exact codes above are non-retryable
	// (e.g. 110600/110620 timeouts are retryable).
	return family === 100 || family === 400;
}

/**
 * Map Turnstile client error codes to a user-facing message.
 *
 * @see https://developers.cloudflare.com/turnstile/troubleshooting/client-side-errors/error-codes/
 *
 * @param {number|string} errorCode Turnstile error code.
 * @return {string} Message for the form error list.
 */
function getTurnstileErrorMessage(errorCode) {
	if (isNonRetryableTurnstileError(errorCode)) {
		return 'Security check could not load. Please refresh the page and try again.';
	}

	return 'Security check failed. Please refresh the page or try a different browser.';
}

/**
 * Tear down a rendered Turnstile widget and clear captcha state for a retry.
 *
 * @param {HTMLElement} target        Captcha container element.
 * @param {Object}      targetContext Parent form interactivity context.
 */
function unmountTurnstileWidget(target, targetContext) {
	const widgetId = target?.dataset?.turnstileWidgetId;
	// eslint-disable-next-line no-undef
	const { turnstile } = window;
	if (widgetId && turnstile && typeof turnstile.remove === 'function') {
		turnstile.remove(widgetId);
	}
	if (target) {
		delete target.dataset.turnstileWidgetId;
		delete target.dataset.turnstileFailures;
	}
	targetContext.captchaToken = '';
	targetContext.captchaPassed = false;
}

store('prc-block/form-captcha', {
	callbacks: {
		onDisplayCaptcha: () => {
			const context = getContext();
			const { targetNamespace } = context;
			const targetContext = getContext(targetNamespace);
			if (!targetContext) {
				return;
			}

			const { ref } = getElement();
			const target = ref?.querySelector(
				'.wp-block-prc-block-form-captcha__captcha'
			);

			const isHidden = targetContext?.captchaHidden;
			// Tear down the widget when the form resets / captcha is hidden again.
			if (true === isHidden) {
				if (target) {
					unmountTurnstileWidget(target, targetContext);
				}
				return;
			}

			if (!target) {
				return;
			}

			// Avoid stacking widgets if the watch callback re-runs while visible.
			if (target.dataset.turnstileWidgetId) {
				return;
			}

			// eslint-disable-next-line no-undef
			const { turnstile, prcFormInputCaptcha } = window;
			const key = prcFormInputCaptcha?.turnstile_key || false;
			if (
				false === key ||
				!turnstile ||
				typeof turnstile.ready !== 'function'
			) {
				pushCaptchaError(
					targetContext,
					'Security check could not load. Please refresh the page and try again.'
				);
				return;
			}

			turnstile.ready(() => {
				if (target.dataset.turnstileWidgetId) {
					return;
				}

				const widgetId = turnstile.render(target, {
					sitekey: key,
					callback: (token) => {
						target.dataset.turnstileFailures = '0';
						clearCaptchaError(targetContext);
						targetContext.captchaToken = token;
						targetContext.captchaPassed = true;
					},
					'expired-callback': () => {
						targetContext.captchaToken = '';
						targetContext.captchaPassed = false;
					},
					// Without error-callback, Turnstile throws TurnstileError (e.g. 300031)
					// into Sentry. Return true only when we surface the error; false lets
					// Turnstile keep auto-retrying for recoverable codes.
					// @see https://developers.cloudflare.com/turnstile/troubleshooting/client-side-errors/
					'error-callback': (errorCode) => {
						targetContext.captchaToken = '';
						targetContext.captchaPassed = false;

						const failures =
							Number(target.dataset.turnstileFailures || 0) + 1;
						target.dataset.turnstileFailures = String(failures);

						const shouldSurface =
							isNonRetryableTurnstileError(errorCode) ||
							failures >= 3;

						if (shouldSurface) {
							pushCaptchaError(
								targetContext,
								getTurnstileErrorMessage(errorCode)
							);
							return true;
						}

						return false;
					},
				});

				if (widgetId) {
					target.dataset.turnstileWidgetId = String(widgetId);
				}
			});
		},
	},
});
