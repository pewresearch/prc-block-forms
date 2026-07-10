/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { useCallback } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { SyncedEntityCreateModal } from '@prc/components';

export default function CreateNewFormModal(props) {
	const { saveEntityRecord } = useDispatch(coreStore);

	const createRecord = useCallback(
		async (title) => {
			const record = await saveEntityRecord('postType', 'form', {
				title,
				status: 'draft',
			});

			return record?.id;
		},
		[saveEntityRecord]
	);

	return (
		<SyncedEntityCreateModal
			{...props}
			title={__('Add New Form', 'prc-block-forms')}
			description={__(
				'Give your form a title to get started.',
				'prc-block-forms'
			)}
			textControlLabel={__('New Form Title', 'prc-block-forms')}
			textPlaceholder={__('e.g. Contact Us', 'prc-block-forms')}
			createButtonLabel={__('Create New Form', 'prc-block-forms')}
			creatingLabel={__('Creating…', 'prc-block-forms')}
			cancelLabel={__('Cancel', 'prc-block-forms')}
			triggerLabel={__('Add New Form', 'prc-block-forms')}
			createRecord={createRecord}
			canSubmit={({ title }) => !!title.trim()}
			getCreateErrorMessage={() =>
				__('Could not create the form.', 'prc-block-forms')
			}
		/>
	);
}
