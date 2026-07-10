<?php
/**
 * EasyNote uninstall cleanup.
 *
 * Removes only EasyNote's own options. User content — notes (posts) and
 * categories (terms) — are standard WordPress objects and are deliberately
 * left intact so the site degrades gracefully back to stock WordPress.
 *
 * @package EasyNote
 */

// Only run when WordPress is genuinely uninstalling this plugin.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'easynote_version' );
