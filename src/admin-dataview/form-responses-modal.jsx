import { Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import ResponsesApp from '../response-admin/app';
import '../response-admin/style.scss';

/**
 * Fullscreen review modal scoped to a single form.
 *
 * Passing `formId` also hides the page heading and the Form column, matching
 * the editor's Review responses panel.
 */
export default function FormResponsesModal({ form, onClose }) {
	if (!form) {
		return null;
	}

	const title = form.title?.trim() || __('Untitled form', 'prc-block-forms');

	return (
		<Modal
			title={title}
			onRequestClose={onClose}
			isFullScreen
			className="prc-forms-library-responses-modal"
		>
			<ResponsesApp formId={form.id} />
		</Modal>
	);
}
