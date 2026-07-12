/**
 * Extends the default @wordpress/scripts webpack config with two entry points:
 * `index` (the logged-in app) and `public` (the small read-only note view).
 *
 * The default config already externalises react/react-dom to WordPress core's
 * registered script handles and emits a *.asset.php manifest per entry, so PHP
 * enqueues the built bundles with the correct dependency array and version hash.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		index: './src/index.js',
		public: './src/public-entry.js',
	},
};
