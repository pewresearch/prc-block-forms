/**
 * WordPress Dependencies
 */
import { useSelect } from '@wordpress/data';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Strip RichText markup for display in the Form Fields inspector list.
 *
 * @param {string|undefined} rawLabel Label attribute (may contain inline HTML).
 * @param {string}           fallback Used when the label is empty after stripping.
 * @return {string}
 */
function toPlainLabel(rawLabel, fallback) {
	const stripped = decodeEntities(
		(rawLabel || '').replace(/<[^>]*>/g, '')
	).trim();

	return stripped || fallback;
}

/**
 * Resolve a human-readable label for a form input block in the field list.
 *
 * @param {Object} block Inner block from the block editor store.
 * @return {string}
 */
function getFormFieldListLabel(block) {
	const metadataName = block.attributes?.metadata?.name || '';
	return toPlainLabel(block.attributes?.label, metadataName || block.name);
}

export function useFormInputBlockDetector(clientId) {
	const foundBlocks = useSelect(
		(select) => {
			const { getBlock, getBlockRootClientId } =
				select('core/block-editor');
			const block = getBlock(clientId);
			if (!block) {
				return [];
			}
			const innerBlocks = block?.innerBlocks;
			const formFields = [];

			// Search function.
			const findFormFields = (blocks, parentClientId) => {
				blocks.forEach((block) => {
					if (block?.name.startsWith('prc-block/form-')) {
						// Check if the name is form-field, if so, ignore.
						if (
							![
								'prc-block/form-field',
								'prc-block/form-page',
								'prc-block/form-message',
								'prc-block/form-submit',
								'prc-block/form-captcha',
							].includes(block.name)
						) {
							if (parentClientId) {
								// Find the parent in the formFields array to add as a subField.
								const parentField = formFields.find(
									(field) => field.blockId === parentClientId
								);
								if (parentField) {
									parentField.subFields.push({
										blockId: block.clientId,
										label: getFormFieldListLabel(block),
										type: block.name,
										name:
											block.attributes?.metadata?.name ||
											'',
										value: block.attributes?.value || '',
										subFields: [],
									});
								} else {
									// If parent not found, add as top-level field.
									formFields.push({
										blockId: block.clientId,
										label: getFormFieldListLabel(block),
										name:
											block.attributes?.metadata?.name ||
											'',
										value: block.attributes?.value || '',
										type: block.name,
										subFields: [],
									});
								}
							} else {
								formFields.push({
									blockId: block.clientId,
									label: getFormFieldListLabel(block),
									type: block.name,
									name:
										block.attributes?.metadata?.name || '',
									value: block.attributes?.value || '',
									subFields: [],
								});
							}
						}
					}
					if (block?.innerBlocks) {
						const parentId = block.clientId;
						findFormFields(block.innerBlocks, parentId);
					}
				});
			};

			// Initialize the search.
			findFormFields(innerBlocks);

			return formFields;
		},
		[clientId]
	);
	return foundBlocks;
}

export function useFormMessageBlockDetector(clientId) {
	const hasMessageBlock = useSelect(
		(select) => {
			const { getBlock } = select('core/block-editor');
			const block = getBlock(clientId);
			const innerBlocks = block?.innerBlocks;
			let found = false;

			// Search function.
			const findMessageBlock = (blocks) => {
				blocks.forEach((block) => {
					if (block.name === 'prc-block/form-message') {
						found = true;
						return;
					}
					if (block.innerBlocks) {
						findMessageBlock(block.innerBlocks);
					}
				});
			};

			// Initialize the search.
			findMessageBlock(innerBlocks);

			return found;
		},
		[clientId]
	);
	return hasMessageBlock;
}
