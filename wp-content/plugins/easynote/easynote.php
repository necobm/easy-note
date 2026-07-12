<?php
/**
 * Plugin Name:       EasyNote
 * Description:       A self-hosted, Notion-like note app for WordPress. Notes are a custom post type organised into a folder tree, edited in a headless React app served on the front end, while wp-admin stays stock.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            EasyNote
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       easynote
 * Domain Path:       /languages
 *
 * @package EasyNote
 */

// Abort if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EASYNOTE_VERSION', '0.1.0' );
define( 'EASYNOTE_FILE', __FILE__ );
define( 'EASYNOTE_DIR', plugin_dir_path( __FILE__ ) );
define( 'EASYNOTE_URL', plugin_dir_url( __FILE__ ) );

require_once EASYNOTE_DIR . 'inc/class-activator.php';
require_once EASYNOTE_DIR . 'inc/class-roles.php';
require_once EASYNOTE_DIR . 'inc/class-post-type.php';
require_once EASYNOTE_DIR . 'inc/class-taxonomy.php';
require_once EASYNOTE_DIR . 'inc/class-notes.php';
require_once EASYNOTE_DIR . 'inc/class-rest-api.php';
require_once EASYNOTE_DIR . 'inc/class-rest-public.php';
require_once EASYNOTE_DIR . 'inc/class-app-shell.php';
require_once EASYNOTE_DIR . 'inc/class-easynote.php';

// Lifecycle hooks must be registered at top level (not inside another hook).
register_activation_hook( __FILE__, array( 'EasyNote_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'EasyNote_Activator', 'deactivate' ) );

/**
 * Boot the plugin once all plugins are loaded.
 */
add_action(
	'plugins_loaded',
	static function () {
		// Translations load just-in-time from the plugin's languages directory
		// (and translate.wordpress.org for hosted installs); no manual
		// load_plugin_textdomain() call is needed since WordPress 4.6.
		EasyNote::instance()->run();
	}
);
