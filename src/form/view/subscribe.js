const ENDPOINT = '/prc-api/v3/mailchimp/subscribe';

const isPreviewRequest = () => {
	const params = new URLSearchParams(window.location.search);
	return params.has('preview') || params.has('preview_id');
};

/**
 * Normalize interest ID(s) for the Mailchimp subscribe API.
 *
 * @param {string|false}          interest  Single interest ID (legacy).
 * @param {string|string[]|false} interests Multiple interest IDs.
 * @return {string|false} Comma-separated interest IDs, or false when empty.
 */
function normalizeInterests(interest, interests) {
	if (Array.isArray(interests) && interests.length > 0) {
		return interests.join(',');
	}
	if (typeof interests === 'string' && interests.length > 0) {
		return interests;
	}
	if (typeof interest === 'string' && interest.length > 0) {
		return interest;
	}
	return false;
}

export default async function subscribe({
	emailAddress,
	captchaToken = false,
	interest = false,
	interests = false,
	formId = false,
	apiKey = 'mailchimp-form',
}) {
	if (isPreviewRequest()) {
		return Promise.resolve({
			success: true,
			message: 'Preview: subscription skipped.',
		});
	}

	const interestsParam = normalizeInterests(interest, interests);

	return new Promise((resolve, reject) => {
		const { apiFetch } = window.wp;
		const { isURL, buildQueryString } = window.wp.url;

		if (!captchaToken) {
			return reject(
				new Error(
					"🙈 We couldn't verify you're not a robot 🤖. Please try again."
				)
			);
		}

		const email = emailAddress;

		const url = document.URL;
		if (!isURL(url)) {
			return reject(new Error('🙈 Invalid page url'));
		}

		const queryParams = {
			email,
			captcha_token: captchaToken,
			interests: interestsParam,
			api_key: apiKey,
			origin_url: url,
		};

		if (formId) {
			queryParams.form_id = formId;
		}

		const path = buildQueryString(queryParams);

		apiFetch({
			path: `${ENDPOINT}/?${path}`,
			method: 'POST',
		})
			.then((response) => {
				if (response.success) {
					return resolve(response);
				}
				return reject(response);
			})
			.catch((error) => reject(error));
	});
}
