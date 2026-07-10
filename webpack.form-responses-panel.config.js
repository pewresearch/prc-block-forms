const defaultConfig = require('../../webpack.config');
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		index: path.resolve(__dirname, 'src/form-responses-panel/index.jsx'),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve(__dirname, 'build/form-responses-panel'),
	},
};
