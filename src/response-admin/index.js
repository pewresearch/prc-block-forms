/**
 * WordPress Dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal Dependencies
 */
import ResponsesApp from './app';
import './style.scss';

document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById('prc-form-responses-admin');
	if (container) {
		const root = createRoot(container);
		root.render(<ResponsesApp />);
	}
});
