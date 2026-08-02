const ENDPOINT = '/prc-api/v3/mailchimp/subscribe';

const isPreviewRequest = () => {
	const params = new URLSearchParams(window.location.search);
	return params.has('preview') || params.has('preview_id');
};

/**
 * Normalize interest ID(s) for the legacy Mailchimp subscribe API.
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

/**
 * Normalize saved-segment ID(s) for the Mailchimp subscribe API.
 *
 * @param {string|false}          segmentId  Single saved segment ID.
 * @param {string|string[]|false} segmentIds Multiple saved segment IDs.
 * @return {string|false} Comma-separated segment IDs, or false when empty.
 */
function normalizeSegmentIds(segmentId, segmentIds) {
	if (Array.isArray(segmentIds) && segmentIds.length > 0) {
		return segmentIds.map(String).join(',');
	}
	if (typeof segmentIds === 'string' && segmentIds.length > 0) {
		return segmentIds;
	}
	if (typeof segmentId === 'string' && segmentId.length > 0) {
		return segmentId;
	}
	return false;
}

export default async function subscribe({
	emailAddress,
	captchaToken = false,
	audienceId = false,
	segmentId = false,
	segmentIds = false,
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
	const segmentIdsParam = normalizeSegmentIds(segmentId, segmentIds);

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
			api_key: apiKey,
			origin_url: url,
		};

		if (audienceId) {
			queryParams.audience_id = audienceId;
		}

		if (segmentIdsParam) {
			queryParams.segment_ids = segmentIdsParam;
		} else if (interestsParam) {
			// Legacy forms still send interest IDs directly.
			queryParams.interests = interestsParam;
		}

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
