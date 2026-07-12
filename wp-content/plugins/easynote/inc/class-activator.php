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
 * Activation is idempotent and non-destructive: it ensures the native `editor`
 * role exists, grants the `easynote_note` capability set to administrators so a
 * lone admin can use the app immediately, and flushes rewrite rules so the
 * front-end app routes (/notes, /n/{slug}) resolve. Deactivation only flushes
 * rewrite rules; notes, folders and roles are left intact.
 */
final class EasyNote_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::ensure_editor_role();

		EasyNote_Roles::grant_caps( 'administrator' );

		// Register the post type, taxonomy and rewrite rules now so the flush
		// below captures them (in case `init` has not run for this request).
		$post_type = new EasyNote_Post_Type();
		$post_type->register_post_type();

		$taxonomy = new EasyNote_Taxonomy();
		$taxonomy->register_taxonomy();

		$app_shell = new EasyNote_App_Shell();
		$app_shell->add_rewrite_rules();

		flush_rewrite_rules();

		update_option( 'easynote_version', EASYNOTE_VERSION );
	}

	/**
	 * Run on plugin deactivation. Non-destructive by design.
	 */
	public static function deactivate() {
		// Remove the custom rewrite rules; leave all user content and roles alone.
		flush_rewrite_rules();
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
