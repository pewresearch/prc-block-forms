/**
 * WordPress Dependencies
 */
import { registerPlugin } from '@wordpress/plugins';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { Button, Modal } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import ResponsesApp from '../response-admin/app';
import '../response-admin/style.scss';

function FormResponsesPanel() {
	const { postType, postId } = useSelect(
		(select) => ({
			postType: select(editorStore).getCurrentPostType(),
			postId: select(editorStore).getCurrentPostId(),
		}),
		[]
	);
	const [open, setOpen] = useState(false);

	if (postType !== 'form') {
		return null;
	}

	return (
		<>
			<PluginDocumentSettingPanel
				name="prc-form-responses"
				title={__('Responses', 'prc-block-forms')}
			>
				<Button variant="secondary" onClick={() => setOpen(true)}>
					{__('Review responses', 'prc-block-forms')}
				</Button>
			</PluginDocumentSettingPanel>
			{open && (
				<Modal
					title={__('Form Responses', 'prc-block-forms')}
					onRequestClose={() => setOpen(false)}
					isFullScreen
				>
					<ResponsesApp formId={postId} />
				</Modal>
			)}
		</>
	);
}

registerPlugin('prc-block-forms-responses-panel', {
	render: FormResponsesPanel,
});
