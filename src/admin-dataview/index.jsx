import { Button, createSlotFill } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import getFormActions from './actions';
import getFormFields, { getDefaultVisibleFields } from './fields';
import FormResponsesModal from './form-responses-modal';
import './style.scss';

const { Fill: HeaderActionsFill } = createSlotFill(
	'prcWpAdminDataview.HeaderActions'
);
const { Fill: PageExtrasFill } = createSlotFill(
	'prcWpAdminDataview.PageExtras'
);
const PAGE_EXTRA_EVENT = 'prcWpAdminDataview.pageExtra';

function isFormList() {
	return 'form' === window?.prcWpAdminDataview?.postType;
}

function canViewResponses() {
	return !!window?.prcWpAdminDataview?.forms?.canViewResponses;
}

function emitPageExtra(type, payload) {
	window.dispatchEvent(
		new CustomEvent(PAGE_EXTRA_EVENT, {
			detail: { type, payload },
		})
	);
}

function FormPageExtras() {
	const [form, setForm] = useState(null);

	useEffect(() => {
		const handlePageExtra = (event) => {
			if ('form-responses' === event.detail?.type) {
				setForm(event.detail.payload);
			}
		};
		window.addEventListener(PAGE_EXTRA_EVENT, handlePageExtra);
		return () =>
			window.removeEventListener(PAGE_EXTRA_EVENT, handlePageExtra);
	}, []);

	return <FormResponsesModal form={form} onClose={() => setForm(null)} />;
}

function FormFills() {
	const newFormUrl =
		window?.prcWpAdminDataview?.forms?.newFormUrl ||
		'post-new.php?post_type=form';

	return (
		<>
			<span>
				{__(
					'Save a form once, embed it anywhere with the Synced Form block, and review its responses here.',
					'prc-block-forms'
				)}
			</span>
			<HeaderActionsFill>
				<Button variant="primary" href={newFormUrl}>
					{__('Add New Form', 'prc-block-forms')}
				</Button>
			</HeaderActionsFill>
			<PageExtrasFill>
				<FormPageExtras />
			</PageExtrasFill>
		</>
	);
}

addFilter('prcWpAdminDataview.fields', 'prc-block-forms/fields', (fields) => {
	if (!isFormList()) {
		return fields;
	}
	return [
		...fields,
		...getFormFields({
			onOpenResponses: (item) => emitPageExtra('form-responses', item),
			canViewResponses: canViewResponses(),
		}),
	];
});

addFilter('prcWpAdminDataview.actions', 'prc-block-forms/actions', (actions) =>
	isFormList()
		? getFormActions(actions, {
				onOpenResponses: (item) =>
					emitPageExtra('form-responses', item),
				canViewResponses: canViewResponses(),
			})
		: actions
);

addFilter(
	'prcWpAdminDataview.defaultVisibleFields',
	'prc-block-forms/default-fields',
	(fields) =>
		isFormList() ? getDefaultVisibleFields(canViewResponses()) : fields
);

addFilter(
	'prcWpAdminDataview.pageDescription',
	'prc-block-forms/page-description',
	(description) => (isFormList() ? <FormFills /> : description)
);

addFilter(
	'prcWpAdminDataview.restQuery',
	'prc-block-forms/rest-query',
	(args, { view }) => {
		if (!isFormList()) {
			return args;
		}

		const mappedArgs = { ...args };

		if ('fieldCount' === view.sort?.field) {
			mappedArgs.orderby = 'fields';
		}

		delete mappedArgs.post_type;
		return mappedArgs;
	}
);
