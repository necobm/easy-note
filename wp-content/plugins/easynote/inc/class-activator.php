<?php
/**
 * Activation / deactivation lifecycle.
 *
 * @package EasyNote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation and deactivation.
 *
 * Activation is idempotent and non-destructive: it only ensures the native
 * `editor` role exists (repairing it from WordPress defaults if it was removed)
 * and records the plugin version. It never invents new capabilities and never
 * touches user content. Deactivation intentionally does nothing destructive.
 */
final class EasyNote_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::ensure_editor_role();
		update_option( 'easynote_version', EASYNOTE_VERSION );
	}

	/**
	 * Run on plugin deactivation. Non-destructive by design.
	 */
	public static function deactivate() {
		// Nothing to tear down: roles, notes and categories are left intact so
		// the site degrades gracefully back to stock WordPress.
	}

	/**
	 * Ensure the native `editor` role exists with its default capabilities.
	 *
	 * WordPress core creates the `editor` role on install, so this is a repair
	 * step for the unusual case where it has been removed. populate_roles() only
	 * adds missing default roles/caps; it does not strip custom capabilities.
	 */
	private static function ensure_editor_role() {
		if ( null === get_role( 'editor' ) ) {
			require_once ABSPATH . 'wp-admin/includes/schema.php';
			populate_roles();
		}
	}
}
