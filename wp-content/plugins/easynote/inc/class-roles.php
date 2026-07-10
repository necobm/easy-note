<?php
/**
 * Role / capability helpers.
 *
 * @package EasyNote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Distinguishes site administrators from "restricted" EasyNote users.
 *
 * The MVP has two roles (admin and editor), but the distinction is made by
 * capability rather than role name so it stays robust: any user who lacks
 * `manage_options` gets the focused EasyNote experience, while administrators
 * keep the full WordPress admin.
 */
final class EasyNote_Roles {

	/** Capability that marks a full site administrator. */
	const ADMIN_CAP = 'manage_options';

	/** Capability required to use EasyNote at all (create/edit notes). */
	const NOTE_CAP = 'edit_posts';

	/**
	 * No runtime hooks needed; the helpers below are static.
	 */
	public function register() {}

	/**
	 * Is the current user a full site administrator?
	 *
	 * @return bool
	 */
	public static function is_site_admin() {
		return current_user_can( self::ADMIN_CAP );
	}

	/**
	 * Is the current user a restricted EasyNote user (editor-like)?
	 *
	 * Logged in, able to edit notes, but not a site administrator.
	 *
	 * @return bool
	 */
	public static function is_restricted_user() {
		return is_user_logged_in()
			&& current_user_can( self::NOTE_CAP )
			&& ! current_user_can( self::ADMIN_CAP );
	}

	/**
	 * Whether a specific user object is a restricted EasyNote user.
	 *
	 * @param WP_User $user User to test.
	 * @return bool
	 */
	public static function user_is_restricted( $user ) {
		return $user instanceof WP_User
			&& $user->has_cap( self::NOTE_CAP )
			&& ! $user->has_cap( self::ADMIN_CAP );
	}
}
