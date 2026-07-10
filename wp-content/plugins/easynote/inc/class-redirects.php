<?php
/**
 * Redirects and admin cleanup for restricted (editor) users.
 *
 * @package EasyNote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps restricted users inside the EasyNote app:
 *
 * - after login they land on the EasyNote home;
 * - visiting the public front end sends them into the app;
 * - disallowed admin screens bounce them back to the app (a UX layer on top of
 *   WordPress's own capability enforcement);
 * - the admin bar and dashboard are tidied of native WordPress items.
 *
 * Administrators and anonymous visitors are never affected.
 */
final class EasyNote_Redirects {

	/**
	 * Admin screens ($pagenow) that restricted users are bounced away from.
	 *
	 * WordPress already blocks these by capability; redirecting simply replaces
	 * the "insufficient permissions" error with the EasyNote home.
	 *
	 * @var string[]
	 */
	private $blocked_pages = array(
		'index.php',            // Dashboard.
		'edit.php',             // Posts list (the app replaces it).
		'edit-comments.php',
		'themes.php',
		'plugins.php',
		'plugin-install.php',
		'users.php',
		'user-new.php',
		'tools.php',
		'import.php',
		'export.php',
		'options-general.php',
		'options-writing.php',
		'options-reading.php',
		'options-discussion.php',
		'options-media.php',
		'options-permalink.php',
	);

	/**
	 * Register hooks.
	 */
	public function register() {
		add_filter( 'login_redirect', array( $this, 'login_redirect' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'guard_admin_screens' ) );
		add_action( 'admin_page_access_denied', array( $this, 'redirect_denied_page' ) );
		add_action( 'template_redirect', array( $this, 'front_end_to_app' ) );
		add_action( 'admin_bar_menu', array( $this, 'clean_admin_bar' ), 999 );
		add_action( 'wp_dashboard_setup', array( $this, 'clean_dashboard' ) );
	}

	/**
	 * Send restricted users to the EasyNote home after login.
	 *
	 * @param string           $redirect_to  Default redirect.
	 * @param string           $requested_to Requested redirect.
	 * @param WP_User|WP_Error $user         Authenticated user.
	 * @return string
	 */
	public function login_redirect( $redirect_to, $requested_to, $user ) {
		if ( EasyNote_Roles::user_is_restricted( $user ) ) {
			return EasyNote_Category_Page::url();
		}
		return $redirect_to;
	}

	/**
	 * Bounce restricted users off disallowed admin screens.
	 */
	public function guard_admin_screens() {
		if ( wp_doing_ajax() || ! EasyNote_Roles::is_restricted_user() ) {
			return;
		}

		global $pagenow;
		if ( in_array( $pagenow, $this->blocked_pages, true ) ) {
			wp_safe_redirect( EasyNote_Category_Page::url() );
			exit;
		}
	}

	/**
	 * Redirect restricted users to the app instead of showing a bare 403.
	 *
	 * Fires from wp-admin/menu.php (before admin_init) for pages the user's
	 * capabilities cannot reach at the menu level — e.g. Settings, Plugins,
	 * Appearance. Administrators never trigger this for those pages.
	 */
	public function redirect_denied_page() {
		if ( EasyNote_Roles::is_restricted_user() ) {
			wp_safe_redirect( EasyNote_Category_Page::url() );
			exit;
		}
	}

	/**
	 * Redirect logged-in restricted users from the public front end into the app.
	 */
	public function front_end_to_app() {
		if ( is_admin() || ! EasyNote_Roles::is_restricted_user() ) {
			return;
		}

		wp_safe_redirect( EasyNote_Category_Page::url() );
		exit;
	}

	/**
	 * Trim native items from the admin bar for restricted users.
	 *
	 * @param WP_Admin_Bar $admin_bar Admin bar instance.
	 */
	public function clean_admin_bar( $admin_bar ) {
		if ( ! EasyNote_Roles::is_restricted_user() ) {
			return;
		}

		foreach ( array( 'wp-logo', 'comments', 'new-content', 'updates' ) as $node ) {
			$admin_bar->remove_node( $node );
		}
	}

	/**
	 * Remove default dashboard widgets for restricted users.
	 */
	public function clean_dashboard() {
		if ( ! EasyNote_Roles::is_restricted_user() ) {
			return;
		}

		global $wp_meta_boxes;
		$wp_meta_boxes['dashboard'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- clearing dashboard widgets for restricted users.
	}
}
