/* eslint-disable max-lines-per-function */
/**
 * External Dependencies
 */
import { Icon } from '@prc/icons';
import styled from '@emotion/styled';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	PanelBody,
	Modal,
	Button,
	ToolbarButton,
	TextControl,
	__experimentalVStack as VStack, // eslint-disable-line
} from '@wordpress/components';
import {
	BlockControls,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useMemo, useCallback, useState, useEffect } from '@wordpress/element';
import { createBlocksFromInnerBlocksTemplate } from '@wordpress/blocks';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal Dependencies
 */
import { BASE_TEMPLATE, DEFAULT_FORM_TEMPLATE } from './constants';
import {
	useFormInputBlockDetector,
	useFormMessageBlockDetector,
} from './utils';
import DefaultActionConfigComponent from './action-config/default-config-component';
import RedirectUrlConfigComponent from './action-config/redirect-url-config';
import {
	buildActionSelectOptions,
	findRegisteredAction,
	getActionPickerHelpText,
} from './action-utils';

const FormFieldsList = styled.ul`
	margin: 0;
	padding: 0;
	list-style: none;
`;

const FormFieldButton = styled(Button)`
	&.components-button {
		width: 100%;
		height: auto;
		min-height: 36px;
		white-space: normal;
		text-align: left;
		word-break: break-word;
		justify-content: flex-start;
	}
`;

function FormFieldPanel({ clientId }) {
	const foundFormInputBlocks = useFormInputBlockDetector(clientId);
	const { selectBlock } = useDispatch(blockEditorStore);
	return (
		<PanelBody title={__('Form Fields')}>
			<FormFieldsList>
				{foundFormInputBlocks.map((block, index) => {
					return (
						<li key={`${block.blockId}-${index}`}>
							<FormFieldButton
								variant="tertiary"
								onClick={() => selectBlock(block.blockId)}
							>
								{block.label}
							</FormFieldButton>
						</li>
					);
				})}
			</FormFieldsList>
		</PanelBody>
	);
}

function ActionConfigPanel({
	selectedAction,
	actionConfig,
	setAttributes,
	clientId,
}) {
	const { configDefaults, ConfigComponent, supportsRedirect } =
		selectedAction;
	const hasConfigDefaults =
		configDefaults && Object.keys(configDefaults).length > 0;

	if (!hasConfigDefaults && !supportsRedirect) {
		return null;
	}

	const config = {
		...(configDefaults || {}),
		...(actionConfig || {}),
	};

	const setConfig = (key, value) => {
		setAttributes({
			actionConfig: {
				...config,
				[key]: value,
			},
		});
	};

	const ConfigPanel = ConfigComponent || DefaultActionConfigComponent;

	return (
		<PanelBody title={__('Action Settings', 'form')} initialOpen>
			<VStack spacing={4}>
				{hasConfigDefaults && (
					<ConfigPanel
						config={config}
						setConfig={setConfig}
						configDefaults={configDefaults}
						clientId={clientId}
					/>
				)}
				{supportsRedirect && (
					<RedirectUrlConfigComponent
						config={config}
						setConfig={setConfig}
					/>
				)}
			</VStack>
		</PanelBody>
	);
}

export default function Controls({
	attributes,
	setAttributes,
	clientId,
	displayMessageEditing,
	setDisplayMessageEditing,
}) {
	const { rootBlockNamespace } = useSelect(
		(select) => {
			const { interactiveNamespace } = attributes;
			const { getBlockRootClientId, getBlock } =
				select('core/block-editor');
			const rootClientId = getBlockRootClientId(clientId);
			const rootBlock = getBlock(rootClientId);
			if (interactiveNamespace && interactiveNamespace.length > 0) {
				return {
					rootBlockNamespace: interactiveNamespace,
				};
			}
			return {
				rootBlockNamespace:
					!rootBlock || rootBlock?.name === 'core/post-content'
						? 'prc-block/form'
						: rootBlock?.name,
			};
		},
		[clientId, attributes]
	);

	const registeredForms = useSelect(
		(select) => select('prc-block-library/forms').getForms(),
		[]
	);

	const { method, action, namespace, formName, actionConfig } = attributes;

	const [_formName, setFormName] = useState(formName);
	const [_action, setAction] = useState(action);
	const [_namespace, setNamespace] = useState(namespace);
	const [_method, setMethod] = useState(method);
	const actionName = useMemo(() => {
		if (!_namespace || !_action) {
			return '';
		}
		return `${_namespace}::${_action}`;
	}, [_namespace, _action]);

	const { replaceInnerBlocks } = useDispatch(blockEditorStore);

	const [isTemplateDialogOpen, setIsTemplateDialogOpen] = useState(false);

	const actionOptions = useMemo(
		() =>
			buildActionSelectOptions(
				registeredForms,
				rootBlockNamespace,
				actionName
			),
		[registeredForms, rootBlockNamespace, actionName]
	);

	const getSelectedAction = useCallback(
		(selectedNamespace, selectedAction) => {
			const form = findRegisteredAction(
				registeredForms,
				selectedNamespace,
				selectedAction
			);

			if (!form) {
				return {};
			}

			if (!form.template || form.template.length === 0) {
				return {
					...form,
					template: DEFAULT_FORM_TEMPLATE,
				};
			}

			return form;
		},
		[registeredForms]
	);

	const selectedAction = useMemo(() => {
		return getSelectedAction(namespace, action);
	}, [namespace, action, getSelectedAction]);

	useEffect(() => {
		setAttributes({
			formName: _formName,
			method: _method,
			action: _action,
			namespace: _namespace,
		});
	}, [_formName, _method, _action, _namespace, setAttributes]);

	useEffect(() => {
		setMethod(method);
		setAction(action);
		setNamespace(namespace);
	}, [formName, method, action, namespace]);

	const hasMessageBlock = useFormMessageBlockDetector(clientId);

	const handleActionChange = (value) => {
		if (!value) {
			return;
		}

		const [nextNamespace, nextAction] = value.split('::');
		const nextForm = findRegisteredAction(
			registeredForms,
			nextNamespace,
			nextAction
		);

		setAction(nextAction);
		setNamespace(nextNamespace);

		if (nextForm?.method) {
			setMethod(nextForm.method);
		}

		const nextActionConfig = {
			...(nextForm?.configDefaults || {}),
			...(nextForm?.supportsRedirect ? { redirectUrl: '' } : {}),
		};

		setAttributes({
			actionConfig: nextActionConfig,
		});

		const hasTemplate = getSelectedAction(
			nextNamespace,
			nextAction
		)?.template;
		if (hasTemplate && hasTemplate.length > 0) {
			setIsTemplateDialogOpen(true);
		}
	};

	return (
		<>
			{hasMessageBlock && (
				<BlockControls group="block">
					<ToolbarButton
						icon={() => (
							<Icon
								icon="message-smile"
								library="solid"
								size="14px"
							/>
						)}
						label={
							displayMessageEditing
								? __('Editing Form Message')
								: __('Editing Form Fields')
						}
						isActive={displayMessageEditing}
						onClick={() =>
							setDisplayMessageEditing(!displayMessageEditing)
						}
					/>
				</BlockControls>
			)}
			<InspectorControls>
				<PanelBody title={__('Form Settings')}>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={__('Form Name')}
						value={_formName}
						onChange={(value) => setFormName(value)}
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={__('Action', 'form')}
						options={actionOptions}
						value={actionName}
						onChange={handleActionChange}
						help={getActionPickerHelpText()}
					/>
					{hasMessageBlock && (
						<Button
							variant="secondary"
							onClick={() =>
								setDisplayMessageEditing(!displayMessageEditing)
							}
						>
							{displayMessageEditing
								? 'Edit Form Fields'
								: 'Edit Form Message'}
						</Button>
					)}
					{isTemplateDialogOpen && (
						<Modal
							isOpen={isTemplateDialogOpen}
							onRequestClose={() =>
								setIsTemplateDialogOpen(false)
							}
							title="Use Form Template?"
							size="medium"
						>
							<p>
								This form includes a default template. To use a
								different set of blocks, click "Start from
								scratch." If you prefer to use the template,
								click "Use template."
							</p>
							<div style={{ display: 'flex', gap: '10px' }}>
								<Button
									variant="primary"
									onClick={() => {
										setIsTemplateDialogOpen(false);
										const blocks =
											createBlocksFromInnerBlocksTemplate(
												selectedAction.template
											);
										replaceInnerBlocks(clientId, [
											...blocks,
										]);
									}}
								>
									Use template
								</Button>
								<Button
									variant="secondary"
									onClick={() => {
										setIsTemplateDialogOpen(false);
									}}
								>
									Use existing blocks
								</Button>
								<Button
									variant="tertiary"
									onClick={() => {
										setIsTemplateDialogOpen(false);
										replaceInnerBlocks(clientId, [
											...createBlocksFromInnerBlocksTemplate(
												BASE_TEMPLATE
											),
										]);
									}}
								>
									Start blank
								</Button>
							</div>
						</Modal>
					)}
				</PanelBody>
				<ActionConfigPanel
					selectedAction={selectedAction}
					actionConfig={actionConfig}
					setAttributes={setAttributes}
					clientId={clientId}
				/>
				<FormFieldPanel clientId={clientId} />
			</InspectorControls>
		</>
	);
}
