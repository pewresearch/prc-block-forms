/**
 * External Dependencies
 */
import { DataViews } from '@wordpress/dataviews';

/**
 * WordPress Dependencies
 */
import { useCallback, useMemo, useState } from '@wordpress/element';
import { Notice, SnackbarList, TabPanel } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import fields from './fields';
import getActions from './actions';
import useResponses from './hooks/use-responses';

const DEFAULT_LAYOUTS = {
	table: {
		layout: {
			primaryField: 'from',
		},
	},
	list: {
		layout: {
			primaryField: 'from',
		},
	},
};

function getDefaultView(formId = 0) {
	const tableFields = formId
		? ['isUnread', 'action', 'status', 'source', 'date']
		: ['isUnread', 'form', 'action', 'status', 'source', 'date'];

	return {
		type: 'table',
		page: 1,
		perPage: 25,
		sort: {
			field: 'date',
			direction: 'desc',
		},
		search: '',
		filters: [],
		titleField: 'from',
		fields: tableFields,
		layout: {
			primaryField: 'from',
		},
	};
}

function Snackbars() {
	const notices = useSelect(
		(select) => select(noticesStore).getNotices(),
		[]
	);
	const { removeNotice } = useDispatch(noticesStore);
	const snackbarNotices = notices.filter(
		(notice) => notice.type === 'snackbar'
	);

	return (
		<SnackbarList
			notices={snackbarNotices}
			onRemove={removeNotice}
			className="prc-form-responses__snackbars"
		/>
	);
}

export default function ResponsesApp({ formId = 0 }) {
	const [view, setView] = useState(() => getDefaultView(formId));
	const [folder, setFolder] = useState('inbox');
	const {
		responses,
		paginationInfo,
		folderCounts,
		isLoading,
		error,
		refresh,
	} = useResponses(view, 'spam' === folder, formId);

	const visibleFields = useMemo(
		() => (formId ? fields.filter((field) => field.id !== 'form') : fields),
		[formId]
	);

	const actions = useMemo(() => getActions(refresh), [refresh]);

	const handleChangeView = useCallback((newView) => {
		setView(newView);
	}, []);

	const handleChangeFolder = useCallback((newFolder) => {
		setFolder(newFolder);
		setView((currentView) => ({ ...currentView, page: 1 }));
	}, []);

	return (
		<div className="prc-form-responses">
			{!formId && (
				<>
					<h1 className="wp-heading-inline">
						{__('Form Responses', 'prc-block-forms')}
					</h1>
					<p className="description">
						{__(
							'View and manage all your form responses in one place.',
							'prc-block-forms'
						)}
					</p>
				</>
			)}
			<TabPanel
				className="prc-form-responses__folders"
				tabs={[
					{
						name: 'inbox',
						title: sprintf(
							/* translators: 1: inbox count, 2: unread count */
							__('Inbox (%1$d · %2$d unread)', 'prc-block-forms'),
							folderCounts.inbox,
							folderCounts.unread || 0
						),
					},
					{
						name: 'spam',
						title: sprintf(
							/* translators: %d: number of spam responses */
							__('Spam (%d)', 'prc-block-forms'),
							folderCounts.spam
						),
					},
				]}
				onSelect={handleChangeFolder}
			>
				{() => null}
			</TabPanel>
			{error ? (
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			) : (
				<DataViews
					data={responses}
					fields={visibleFields}
					view={view}
					onChangeView={handleChangeView}
					defaultLayouts={DEFAULT_LAYOUTS}
					actions={actions}
					paginationInfo={paginationInfo}
					isLoading={isLoading}
					search
					searchLabel={__('Search responses…', 'prc-block-forms')}
					getItemId={(item) => item.id.toString()}
				/>
			)}
			<Snackbars />
		</div>
	);
}
