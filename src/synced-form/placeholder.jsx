/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { SyncedEntityPlaceholder } from '@prc/components';

/**
 * Internal Dependencies
 */
import FormSearch from './form-search';
import CreateNewFormModal from './create-new-form-modal';

export default function Placeholder({ setAttributes, isNew, isResolving }) {
	return (
		<SyncedEntityPlaceholder
			setAttributes={setAttributes}
			isNew={isNew}
			isResolving={isResolving}
			label={__('Synced Form', 'prc-block-forms')}
			instructions={__(
				'Search for an existing form or create a new one. Responses are collected centrally under Forms → Responses.',
				'prc-block-forms'
			)}
			icon="feedback"
			loadingLabel={__('Loading Form…', 'prc-block-forms')}
			createButtonLabel={__('Create New Form', 'prc-block-forms')}
			renderSearch={() => <FormSearch setAttributes={setAttributes} />}
			renderCreateModal={(modalProps) => (
				<CreateNewFormModal {...modalProps} hideTrigger />
			)}
		/>
	);
}
