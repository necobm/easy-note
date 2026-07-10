<?php
/**
 * Admin-menu registration and native-menu replacement.
 *
 * @package EasyNote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the single EasyNote admin screen and, for restricted (editor)
 * users, removes the native WordPress admin menu items so only the EasyNote
 * workspace remains. Administrators keep the full menu plus an EasyNote entry.
 */
final class EasyNote_Admin_Menu {

	/**
	 * The screen that renders category/home views.
	 *
	 * @var EasyNote_Category_Page
	 */
	private $category_page;

	/**
	 * Native top-level menu slugs removed for restricted users.
	 *
	 * @var string[]
	 */
	private $native_menus = array(
		'index.php',                     // Dashboard.
		'edit.php',                      // Posts.
		'upload.php',                    // Media.
		'edit.php?post_type=page',       // Pages.
		'edit-comments.php',             // Comments.
		'themes.php',                    // Appearance.
		'plugins.php',                   // Plugins.
		'users.php',                     // Users.
		'tools.php',                     // Tools.
		'options-general.php',           // Settings.
	);

	/**
	 * Constructor.
	 *
	 * @param EasyNote_Category_Page $category_page Screen renderer.
	 */
	public function __construct( EasyNote_Category_Page $category_page ) {
		$this->category_page = $category_page;
	}

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		// Run late so every native menu has already been registered.
		add_action( 'admin_menu', array( $this, 'maybe_remove_native_menus' ), 999 );
	}

	/**
	 * Register the single EasyNote top-level page.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'EasyNote', 'easynote' ),
			__( 'EasyNote', 'easynote' ),
			EasyNote_Roles::NOTE_CAP,
			EASYNOTE_PAGE,
			array( $this, 'render_page' ),
			'dashicons-book-alt',
			3
		);
	}

	/**
	 * Remove native menu items for restricted users only.
	 */
	public function maybe_remove_native_menus() {
		if ( EasyNote_Roles::is_site_admin() ) {
			return; // Administrators keep the full WordPress menu.
		}

		foreach ( $this->native_menus as $menu_slug ) {
			remove_menu_page( $menu_slug );
		}
	}

	/**
	 * Render callback for the EasyNote screen. Reads and sanitises the node id.
	 */
	public function render_page() {
		$node = isset( $_GET['node'] ) ? absint( wp_unslash( $_GET['node'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation param.
		$this->category_page->render( $node );
	}
}
