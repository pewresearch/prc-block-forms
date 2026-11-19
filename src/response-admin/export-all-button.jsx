/**
 * WordPress Dependencies
 */
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { download } from '@wordpress/icons';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal Dependencies
 */
import fetchAllResponses from './hooks/fetch-all-responses';
import { exportResponsesToCsv } from './utils';

/**
 * Download every response that matches the current view as one CSV.
 *
 * @param {Object} props                Props.
 * @param {Object} props.queryArgs      List query args for the current view.
 * @param {number} props.totalItems     Matching responses in the current view.
 * @param {string} props.filenameSuffix Slug appended to the file name.
 */
export default function ExportAllButton({
	queryArgs,
	totalItems,
	filenameSuffix,
}) {
	const { createErrorNotice, createSuccessNotice } =
		useDispatch(noticesStore);
	const [progress, setProgress] = useState(null);
	const controllerRef = useRef(null);

	useEffect(() => () => controllerRef.current?.abort(), []);

	const isExporting = null !== progress;

	const handleExport = async () => {
		const controller = new AbortController();
		controllerRef.current = controller;
		setProgress({ loaded: 0, total: totalItems });

		try {
			const items = await fetchAllResponses(queryArgs, {
				signal: controller.signal,
				onProgress: (loaded, total) => setProgress({ loaded, total }),
			});
			exportResponsesToCsv(items, filenameSuffix);
			createSuccessNotice(
				sprintf(
					/* translators: %d: number of exported responses */
					_n(
						'%d response exported.',
						'%d responses exported.',
						items.length,
						'prc-block-forms'
					),
					items.length
				),
				{ type: 'snackbar' }
			);
		} catch (err) {
			if ('AbortError' === err?.name) {
				createSuccessNotice(__('Export canceled.', 'prc-block-forms'), {
					type: 'snackbar',
				});
			} else {
				createErrorNotice(
					__('Could not export the responses.', 'prc-block-forms'),
					{ type: 'snackbar' }
				);
			}
		} finally {
			controllerRef.current = null;
			setProgress(null);
		}
	};

	if (isExporting) {
		return (
			<Button
				__next40pxDefaultSize
				variant="secondary"
				isBusy
				onClick={() => controllerRef.current?.abort()}
			>
				{sprintf(
					/* translators: 1: responses loaded, 2: total responses */
					__('Exporting… %1$d of %2$d · Cancel', 'prc-block-forms'),
					progress.loaded,
					progress.total
				)}
			</Button>
		);
	}

	return (
		<Button
			__next40pxDefaultSize
			variant="secondary"
			icon={download}
			disabled={!totalItems}
			accessibleWhenDisabled
			onClick={handleExport}
		>
			{sprintf(
				/* translators: %d: number of responses that match the view */
				__('Export all (%d)', 'prc-block-forms'),
				totalItems
			)}
		</Button>
	);
}
