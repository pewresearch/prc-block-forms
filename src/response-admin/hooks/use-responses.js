/**
 * WordPress Dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

/**
 * Translate the DataViews `view` object into query args for the
 * `prc-api/v3/form/responses` list endpoint.
 *
 * The "form" filter value is prefixed: `id:<post id>` targets the form CPT
 * (`form_id` column), `name:<form name>` targets legacy inline forms
 * (`form_name` column).
 *
 * @param {Object}  view   The DataViews view object.
 * @param {boolean} isSpam Whether the spam folder is active.
 * @return {Object} Query args.
 */
function viewToQueryArgs(view, isSpam) {
	const args = {
		per_page: view.perPage || 25,
		page: view.page || 1,
		order: view.sort?.direction || 'desc',
		is_spam: isSpam ? 1 : 0,
	};

	if (view.search) {
		args.search = view.search;
	}

	if (view.filters?.length) {
		view.filters.forEach((filter) => {
			if (!filter.value && filter.value !== 0 && filter.value !== false) {
				return;
			}
			if (filter.field === 'form') {
				const value = String(filter.value);
				if (value.startsWith('id:')) {
					args.form_id = parseInt(value.slice(3), 10);
				}
				if (value.startsWith('name:')) {
					args.form_name = value.slice(5);
				}
			}
			if (filter.field === 'status') {
				const values = Array.isArray(filter.value)
					? filter.value
					: [filter.value];
				const joined = values.filter(Boolean).join(',');
				if (joined) {
					args.status = joined;
				}
			}
			if (filter.field === 'isUnread') {
				args.is_unread =
					filter.value === true ||
					filter.value === 'true' ||
					filter.value === 1 ||
					filter.value === '1'
						? 1
						: 0;
			}
		});
	}

	return args;
}

export default function useResponses(view, isSpam = false, formId = 0) {
	const [responses, setResponses] = useState([]);
	const [isLoading, setIsLoading] = useState(true);
	const [error, setError] = useState(null);
	const [paginationInfo, setPaginationInfo] = useState({
		totalItems: 0,
		totalPages: 1,
	});
	const [folderCounts, setFolderCounts] = useState({
		inbox: 0,
		spam: 0,
		unread: 0,
	});

	const [refreshToken, setRefreshToken] = useState(0);
	const refresh = useCallback(() => setRefreshToken((n) => n + 1), []);

	const abortRef = useRef(null);

	useEffect(() => {
		if (abortRef.current) {
			abortRef.current.abort();
		}
		const controller = new AbortController();
		abortRef.current = controller;

		setIsLoading(true);
		setError(null);

		const args = viewToQueryArgs(view, isSpam);
		if (formId) {
			args.form_id = formId;
		}

		const path = addQueryArgs('/prc-api/v3/form/responses', args);

		apiFetch({ path, signal: controller.signal, parse: false })
			.then(async (response) => {
				const total = parseInt(
					response.headers.get('X-WP-Total') || '0',
					10
				);
				const totalPages = parseInt(
					response.headers.get('X-WP-TotalPages') || '1',
					10
				);
				const inbox = parseInt(
					response.headers.get('X-PRC-Inbox-Total') || '0',
					10
				);
				const spam = parseInt(
					response.headers.get('X-PRC-Spam-Total') || '0',
					10
				);
				const unread = parseInt(
					response.headers.get('X-PRC-Unread-Total') || '0',
					10
				);
				const data = await response.json();
				setResponses(data);
				setPaginationInfo({ totalItems: total, totalPages });
				setFolderCounts({ inbox, spam, unread });
			})
			.catch((err) => {
				if (err.name !== 'AbortError') {
					setError(err.message || 'Failed to load form responses.');
					setResponses([]);
				}
			})
			.finally(() => {
				if (!controller.signal.aborted) {
					setIsLoading(false);
				}
			});

		return () => controller.abort();
	}, [view, isSpam, formId, refreshToken]);

	return {
		responses,
		paginationInfo,
		folderCounts,
		isLoading,
		error,
		refresh,
	};
}
