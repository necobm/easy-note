<?php
/**
 * The `easynote_folder` taxonomy and its term meta.
 *
 * @package EasyNote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the hierarchical folder taxonomy that organises notes into a tree.
 *
 * Folders are shared across the workspace (WordPress terms are not per-user);
 * per-user privacy is enforced at the note level via `post_author`. Like Notion
 * pages, a folder can carry its own document body, stored as a Yoopta JSON
 * string in the mirrored `_easynote_content` term meta.
 */
final class EasyNote_Taxonomy {

	/** Taxonomy name. */
	const TAXONOMY = 'easynote_folder';

	/** Term meta key holding the folder's own Yoopta body as a JSON string. */
	const CONTENT_META = '_easynote_content';

	/**
	 * Wire up hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Register the `easynote_folder` taxonomy on the note post type.
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'                  => _x( 'Folders', 'taxonomy general name', 'easynote' ),
			'singular_name'         => _x( 'Folder', 'taxonomy singular name', 'easynote' ),
			'menu_name'             => __( 'Folders', 'easynote' ),
			'all_items'             => __( 'All Folders', 'easynote' ),
			'edit_item'             => __( 'Edit Folder', 'easynote' ),
			'update_item'           => __( 'Update Folder', 'easynote' ),
			'add_new_item'          => __( 'Add New Folder', 'easynote' ),
			'new_item_name'         => __( 'New Folder Name', 'easynote' ),
			'parent_item'           => __( 'Parent Folder', 'easynote' ),
			'parent_item_colon'     => __( 'Parent Folder:', 'easynote' ),
			'search_items'          => __( 'Search Folders', 'easynote' ),
			'not_found'             => __( 'No folders found.', 'easynote' ),
			'items_list'            => __( 'Folders list', 'easynote' ),
			'items_list_navigation' => __( 'Folders list navigation', 'easynote' ),
		);

		register_taxonomy(
			self::TAXONOMY,
			array( EasyNote_Post_Type::POST_TYPE ),
			array(
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'hierarchical'       => true,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'rest_base'          => 'easynote-folders',
				'query_var'          => false,
				'rewrite'            => false,
			)
		);
	}

	/**
	 * Register the folder body meta, mirroring the note content meta.
	 */
	public function register_meta() {
		register_term_meta(
			self::TAXONOMY,
			self::CONTENT_META,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '{}',
				'show_in_rest'      => true,
				'sanitize_callback' => array( 'EasyNote_Post_Type', 'sanitize_content' ),
				'auth_callback'     => array( __CLASS__, 'auth_edit' ),
			)
		);
	}

	/**
	 * Meta auth: may the user manage folders?
	 *
	 * @return bool
	 */
	public static function auth_edit() {
		return current_user_can( 'manage_categories' );
	}
}
