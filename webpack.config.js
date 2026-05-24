const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'command-bar': './assets/js/src/index.js',
	},
	output: {
		path: path.resolve( __dirname, 'build' ),
		filename: '[name].js',
		library: 'WPPowerStack',
		libraryTarget: 'window',
		globalObject: 'window',
	},
	module: {
		...defaultConfig.module,
		rules: [
			...defaultConfig.module.rules,
			{
				test: /\.scss$/,
				use: ['style-loader', 'css-loader', 'sass-loader'],
			},
		],
	},
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...defaultConfig.resolve.alias,
			'@': path.resolve( __dirname, 'assets/js/src' ),
		},
	},
	externals: {
		'@wordpress/element': 'wp.element',
		'@wordpress/data': 'wp.data',
		'@wordpress/components': 'wp.components',
		'@wordpress/i18n': 'wp.i18n',
	},
	optimization: {
		...defaultConfig.optimization,
		splitChunks: {
			cacheGroups: {
				fuse: {
					test: /[\\/]node_modules[\\/]fuse\.js[\\/]/,
					name: 'fuse',
					chunks: 'all',
					priority: 10,
					enforce: true,
				},
			},
		},
	},
	performance: {
		hints: false, // Disable bundle size warnings for development
	},
};
