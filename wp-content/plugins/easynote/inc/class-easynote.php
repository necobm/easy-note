<?php
/**
 * Plugin loader / dependency container.
 *
 * @package EasyNote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Instantiates each feature component and registers its hooks.
 *
 * Components are created in the constructor (no hooks added there); hooks are
 * only wired up when run() is called on `plugins_loaded`.
 */
final class EasyNote {

	/**
	 * Singleton instance.
	 *
	 * @var EasyNote|null
	 */
	private static $instance = null;

	/**
	 * Feature components, keyed by name.
	 *
	 * @var array<string,object>
	 */
	private $components = array();

	/**
	 * Get the singleton instance.
	 *
	 * @return EasyNote
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire up the components (no side effects beyond object creation).
	 */
	private function __construct() {
		$category_page = new EasyNote_Category_Page();

		$this->components['roles']         = new EasyNote_Roles();
		$this->components['notes']         = new EasyNote_Notes();
		$this->components['category_page'] = $category_page;
		$this->components['sidebar_tree']  = new EasyNote_Sidebar_Tree();
		$this->components['admin_menu']    = new EasyNote_Admin_Menu( $category_page );
		$this->components['redirects']     = new EasyNote_Redirects();
	}

	/**
	 * Register every component's hooks.
	 */
	public function run() {
		foreach ( $this->components as $component ) {
			if ( method_exists( $component, 'register' ) ) {
				$component->register();
			}
		}
	}

	/**
	 * Access a component by key (mainly for tests / introspection).
	 *
	 * @param string $key Component key.
	 * @return object|null
	 */
	public function get( $key ) {
		return isset( $this->components[ $key ] ) ? $this->components[ $key ] : null;
	}
}
