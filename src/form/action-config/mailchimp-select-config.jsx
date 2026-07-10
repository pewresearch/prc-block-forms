/**
 * External Dependencies
 */
import { MailchimpSegmentList } from '@prc/components';

/**
 * WordPress Dependencies
 */
import { store as blockEditorStore } from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { PanelRow } from '@wordpress/components';

const findMatchingCheckboxBlockField = (blocks, value) =>
	blocks.find((block) => {
		if (block.name !== 'prc-block/form-input-checkbox') {
			return false;
		}
		return block.attributes.value === value;
	});

/**
 * Config UI for the Newsletter Selection (Mailchimp) form action.
 *
 * @param {Object}   props
 * @param {Object}   props.config    Current action config values.
 * @param {Function} props.setConfig Updates a single config key.
 * @param {string}   props.clientId  Form block client ID for inner block edits.
 */
export default function MailchimpSelectConfigComponent({
	config,
	setConfig,
	clientId,
}) {
	const interests = config?.interests ?? [];

	const { insertBlock, removeBlock, updateBlockAttributes } =
		useDispatch(blockEditorStore);

	const innerBlocks = useSelect(
		(select) =>
			select(blockEditorStore).getBlock(clientId)?.innerBlocks ?? [],
		[clientId]
	);

	const onRemove = (item) => {
		const matchingBlock = findMatchingCheckboxBlockField(
			innerBlocks,
			item.value
		);
		if (matchingBlock) {
			updateBlockAttributes(matchingBlock.clientId, {
				lock: {
					remove: false,
				},
			});
			removeBlock(matchingBlock.clientId, false);
		}
	};

	const onAdd = (item) => {
		const inputBlock = createBlock('prc-block/form-input-checkbox', {
			value: item.value,
			lock: {
				remove: true,
			},
			label: item.label,
			type: 'checkbox',
			metadata: {
				name: item.label
					.replace(/[^a-z0-9\s]/gi, '')
					.toLowerCase()
					.replace(/\s/g, '_'),
			},
		});
		insertBlock(inputBlock, false, clientId, false);
	};

	const onUpdate = (updatedSelected) => {
		setConfig('interests', [...updatedSelected]);
	};

	return (
		<PanelRow>
			<MailchimpSegmentList
				interests={interests}
				onAdd={onAdd}
				onRemove={onRemove}
				onUpdate={onUpdate}
				apiKey="mailchimp-select"
			/>
		</PanelRow>
	);
}
