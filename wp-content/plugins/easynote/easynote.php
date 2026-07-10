<?php
/**
 * Plugin Name:       EasyNote
 * Plugin URI:        https://example.com/easynote
 * Description:       Turns WordPress into a self-hosted, Notion-like note app. Categories become a nestable note tree, posts become notes, and editors get a focused admin UI. No core files are modified.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            EasyNote
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       easynote
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

/** Admin page slug for the single EasyNote screen (admin.php?page=easynote). */
define( 'EASYNOTE_PAGE', 'easynote' );

require_once EASYNOTE_DIR . 'inc/class-activator.php';
require_once EASYNOTE_DIR . 'inc/class-roles.php';
require_once EASYNOTE_DIR . 'inc/class-notes.php';
require_once EASYNOTE_DIR . 'inc/class-sidebar-tree.php';
require_once EASYNOTE_DIR . 'inc/class-category-page.php';
require_once EASYNOTE_DIR . 'inc/class-admin-menu.php';
require_once EASYNOTE_DIR . 'inc/class-redirects.php';
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
		EasyNote::instance()->run();
	}
);
