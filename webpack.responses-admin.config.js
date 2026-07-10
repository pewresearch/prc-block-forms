const defaultConfig = require('../../webpack.config');
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		index: path.resolve(__dirname, 'src/response-admin/index.js'),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve(__dirname, 'build/response-admin'),
	},
};
