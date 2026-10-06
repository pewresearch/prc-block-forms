/**
 * External Dependencies
 */
import clsx from 'clsx';
import { Icon } from '@prc/icons';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	store as blockEditorStore,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

const TEMPLATE = [
	[
		'core/button',
		{
			text: __('Submit'),
			tagName: 'button',
			type: 'submit',
		},
	],
	['prc-block/form-captcha', {}],
];

/**
 * Count `prc-block/form-page` blocks anywhere under the given blocks.
 *
 * @param {Array} blocks Block list.
 * @return {number} Page count.
 */
function countFormPages(blocks = []) {
	return blocks.reduce(
		(total, block) =>
			total +
			('prc-block/form-page' === block.name ? 1 : 0) +
			countFormPages(block.innerBlocks),
		0
	);
}

/**
 * Static stand-in for the page navigation the server renders on paginated
 * forms. It mirrors the frontend markup, including the registry icon names the
 * PHP render uses, so authors see what readers get.
 *
 * @return {Element} Preview markup.
 */
function PageNavigationPreview() {
	return (
		<div
			className="wp-block-prc-block-form-submit__page-nav"
			contentEditable={false}
		>
			<span className="wp-block-prc-block-form-submit__page-nav-link is-previous-page">
				<span
					className="wp-block-prc-block-form-submit__page-nav-icon"
					aria-hidden="true"
				>
					<Icon library="prc" icon="angle-left" size={0.75} />
				</span>
				<span>{__('Go back to previous question')}</span>
			</span>
			<span className="wp-block-prc-block-form-submit__page-nav-link is-reset">
				<span
					className="wp-block-prc-block-form-submit__page-nav-icon"
					aria-hidden="true"
				>
					<Icon library="prc" icon="arrow-rotate-left" size={0.75} />
				</span>
				<span>{__('Reset')}</span>
			</span>
		</div>
	);
}

export default function Edit({
	clientId,
	__unstableLayoutClassNames: layoutClassNames,
}) {
	const isPaginated = useSelect(
		(select) => {
			const { getBlockParentsByBlockName, getBlock } =
				select(blockEditorStore);
			const [formClientId] = getBlockParentsByBlockName(
				clientId,
				'prc-block/form'
			);
			if (!formClientId) {
				return false;
			}
			return countFormPages(getBlock(formClientId)?.innerBlocks) > 1;
		},
		[clientId]
	);

	const blockProps = useBlockProps({
		className: clsx(layoutClassNames),
	});
	const { children, ...innerBlocksProps } = useInnerBlocksProps(blockProps, {
		template: TEMPLATE,
		templateLock: 'all',
		renderAppender: false,
	});
	return (
		<div {...innerBlocksProps}>
			{children}
			{isPaginated && <PageNavigationPreview />}
		</div>
	);
}
