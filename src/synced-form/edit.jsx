/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { SyncedEntityEdit } from '@prc/components';

/**
 * Internal Dependencies
 */
import Controls from './controls';
import Placeholder from './placeholder';

const PRESENCE_MESSAGES = {
	/* translators: %s: display name of the user currently editing the form */
	singular: __('%s is currently editing this form.', 'prc-block-forms'),
	/* translators: 1: comma-separated list of names, 2: last name in the list */
	plural: __(
		'%1$s and %2$s are currently editing this form.',
		'prc-block-forms'
	),
};

export default function SyncedFormEdit(props) {
	return (
		<SyncedEntityEdit
			{...props}
			postType="form"
			presenceMessages={PRESENCE_MESSAGES}
			presenceNoticeClassName="synced-form-presence__notice"
			labels={{
				recursionWarning: __(
					'Form cannot be rendered inside itself.',
					'prc-block-forms'
				),
				deletedWarning: __(
					'Form has been deleted or is unavailable.',
					'prc-block-forms'
				),
				emptyLabel: __('Empty Form', 'prc-block-forms'),
			}}
			Controls={Controls}
			Placeholder={Placeholder}
		/>
	);
}
