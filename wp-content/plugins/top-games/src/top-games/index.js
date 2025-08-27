/**
 * Registers the block using metadata from block.json.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';   // <-- IMPORTANT: block.json is at the plugin root

/**
 * Internal deps
 */
import Edit from './edit';

/**
 * Styles:
 * - style.scss -> front + editor
 * - editor.scss -> editor only
 */
import './style.scss';
import './editor.scss';

// Dynamic block: PHP renders, nothing is saved to HTML
const save = () => null;

registerBlockType( metadata.name, {
	edit: Edit,
	save,
} );
