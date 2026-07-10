/**
 * WordPress Dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal Dependencies
 */
import metadata from './block.json';
import edit from './edit';
import icon from './icon';

const { name } = metadata;

const settings = {
	icon,
	edit,
};

registerBlockType(name, { ...metadata, ...settings });
