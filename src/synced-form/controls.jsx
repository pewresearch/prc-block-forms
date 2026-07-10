/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { SyncedEntityIsolationControls } from '@prc/components';

export default function Controls({ attributes, entityTitle = '' }) {
	return (
		<SyncedEntityIsolationControls
			attributes={attributes}
			panelTitle={__('Synced Form', 'prc-block-forms')}
			entityTitle={entityTitle}
			entityTitleLabel={__('Form Title', 'prc-block-forms')}
			labels={{
				edit: __('Edit form in isolation', 'prc-block-forms'),
			}}
		/>
	);
}
