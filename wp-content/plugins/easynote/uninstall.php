<?php
/**
 * EasyNote uninstall cleanup.
 *
 * Removes EasyNote's own option and the note capabilities it granted. User
 * content — notes (easynote_note posts) and folders (easynote_folder terms) —
 * is deliberately left intact so nothing is destroyed on uninstall.
 *
 * @package EasyNote
 */

// Only run when WordPress is genuinely uninstalling this plugin.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/inc/class-roles.php';

/**
 * Perform uninstall cleanup (wrapped in a function to avoid polluting the global
 * scope with loop variables).
 */
function easynote_uninstall_cleanup() {
	foreach ( array( 'administrator', 'editor' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( null === $role ) {
			continue;
		}
		foreach ( EasyNote_Roles::note_caps() as $cap ) {
			$role->remove_cap( $cap );
		}
	}

	delete_option( 'easynote_version' );
}

easynote_uninstall_cleanup();
