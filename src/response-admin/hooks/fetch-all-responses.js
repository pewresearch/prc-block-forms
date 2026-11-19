/**
 * WordPress Dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const PER_PAGE = 100;
const CONCURRENCY = 4;

/**
 * Fetch every response that matches the given list query args, page by page.
 *
 * `before` freezes the result set at the server time of a one-row probe (its
 * `Date` header), the same clock that wrote `created_at`. Every page is
 * fetched with it. `created_at` has one-second resolution and the bound is
 * exclusive, so it is the start of the next second. Rows that arrive during
 * the export cannot shift the pages, and rows are deduplicated by id.
 *
 * @param {Object}      args               Query args from `viewToQueryArgs`.
 * @param {Object}      options            Options.
 * @param {Function}    options.onProgress Called with (loaded, total) after each page.
 * @param {AbortSignal} options.signal     Abort signal to cancel the export.
 * @return {Promise<Array>} Every matching response, in the requested order.
 */
export default async function fetchAllResponses(
	args,
	{ onProgress, signal } = {}
) {
	const queryArgs = {
		...args,
		per_page: PER_PAGE,
	};

	const byId = new Map();

	const fetchPage = async (page) => {
		const response = await apiFetch({
			path: addQueryArgs('/prc-api/v3/form/responses', {
				...queryArgs,
				page,
			}),
			signal,
			parse: false,
		});
		const items = await response.json();
		items.forEach((item) => byId.set(item.id, item));
		return response;
	};

	const probe = await apiFetch({
		path: addQueryArgs('/prc-api/v3/form/responses', {
			...queryArgs,
			per_page: 1,
		}),
		signal,
		parse: false,
	});
	const serverNow = Date.parse(probe.headers.get('Date') || '');
	queryArgs.before = new Date(
		(Math.floor((serverNow || Date.now()) / 1000) + 1) * 1000
	).toISOString();

	const first = await fetchPage(1);
	const total = parseInt(first.headers.get('X-WP-Total') || '0', 10);
	const totalPages = parseInt(
		first.headers.get('X-WP-TotalPages') || '1',
		10
	);
	onProgress?.(byId.size, total);

	let nextPage = 2;
	const worker = async () => {
		while (nextPage <= totalPages) {
			const page = nextPage++;
			await fetchPage(page);
			onProgress?.(Math.min(byId.size, total), total);
		}
	};

	await Promise.all(
		Array.from(
			{ length: Math.min(CONCURRENCY, Math.max(0, totalPages - 1)) },
			worker
		)
	);

	const direction = 'asc' === String(args.order).toLowerCase() ? 1 : -1;
	return [...byId.values()].sort((a, b) => {
		if (a.date !== b.date) {
			return (a.date < b.date ? -1 : 1) * direction;
		}
		return (a.id - b.id) * direction;
	});
}
