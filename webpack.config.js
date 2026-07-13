const defaultConfig = require( "@wordpress/scripts/config/webpack.config" );
const path = require( "path" );
const RemoveEmptyScriptsPlugin = require( "webpack-remove-empty-scripts" );

module.exports = {
	...defaultConfig,
	entry: {
		"js/alg-wc-product-xml-feeds-admin": "./assets/js/alg-wc-product-xml-feeds-admin.js",
		"js/alg-wc-product-xml-feeds-admin-own": "./assets/js/alg-wc-product-xml-feeds-admin-own.js",
		"css/alg-wc-product-xml-feeds-admin": "./assets/css/alg-wc-product-xml-feeds-admin.css",
	},
	output: {
		path: path.resolve( __dirname, "assets/build" ),
		clean: true,
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( p ) =>
				p.constructor.name !== "DependencyExtractionWebpackPlugin" // remove asset.php
				&& p.constructor.name !== "RtlCssPlugin"  // remove rtl CSS
		),
		new RemoveEmptyScriptsPlugin()
	],
};