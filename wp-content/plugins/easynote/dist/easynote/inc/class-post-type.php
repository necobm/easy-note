<?php
/**
 * The `easynote_note` custom post type and its meta.
 *
 * @package EasyNote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the note post type and the meta fields that back the headless app.
 *
 * A note's body is the Yoopta editor value, stored as a JSON string in the
 * `_easynote_content` meta (Yoopta keys blocks by dynamic ids, which does not
 * map onto a fixed REST object schema — a JSON string keeps the transport
 * simple and lossless). Visibility is a separate boolean `_easynote_public`
 * flag that only the note's author may flip.
 */
final class EasyNote_Post_Type {

	/** Post type name. */
	const POST_TYPE = 'easynote_note';

	/** Meta key holding the Yoopta editor value as a JSON string. */
	const CONTENT_META = '_easynote_content';

	/** Meta key holding the public-visibility flag. */
	const PUBLIC_META = '_easynote_public';

	/**
	 * Wire up hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Register the `easynote_note` post type.
	 *
	 * `public` is false (no theme templates/archives), but `publicly_queryable`
	 * stays true so a single public note can be resolved by the custom `/n/{slug}`
	 * route. `show_ui` is true so administrators keep a stock wp-admin fallback.
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => _x( 'Notes', 'post type general name', 'easynote' ),
			'singular_name'         => _x( 'Note', 'post type singular name', 'easynote' ),
			'menu_name'             => _x( 'Notes', 'admin menu', 'easynote' ),
			'name_admin_bar'        => _x( 'Note', 'add new on admin bar', 'easynote' ),
			'add_new'               => __( 'Add New', 'easynote' ),
			'add_new_item'          => __( 'Add New Note', 'easynote' ),
			'new_item'              => __( 'New Note', 'easynote' ),
			'edit_item'             => __( 'Edit Note', 'easynote' ),
			'view_item'             => __( 'View Note', 'easynote' ),
			'all_items'             => __( 'All Notes', 'easynote' ),
			'search_items'          => __( 'Search Notes', 'easynote' ),
			'not_found'             => __( 'No notes found.', 'easynote' ),
			'not_found_in_trash'    => __( 'No notes found in Trash.', 'easynote' ),
			'items_list'            => __( 'Notes list', 'easynote' ),
			'items_list_navigation' => __( 'Notes list navigation', 'easynote' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => 'notes',
				'menu_icon'          => 'dashicons-media-document',
				'menu_position'      => 20,
				'hierarchical'       => false,
				'has_archive'        => false,
				'rewrite'            => false,
				'query_var'          => false,
				'supports'           => array( 'title', 'author', 'revisions', 'custom-fields' ),
				'capability_type'    => array( 'easynote_note', 'easynote_notes' ),
				'map_meta_cap'       => true,
			)
		);
	}

	/**
	 * Register the note meta fields exposed to REST.
	 */
	public function register_meta() {
		register_post_meta(
			self::POST_TYPE,
			self::CONTENT_META,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '{}',
				'show_in_rest'      => true,
				'revisions_enabled' => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_content' ),
				'auth_callback'     => array( __CLASS__, 'auth_edit' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::PUBLIC_META,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => array( __CLASS__, 'auth_author_only' ),
			)
		);
	}

	/**
	 * Normalise the Yoopta content JSON string.
	 *
	 * Guarantees the stored value is a JSON-encoded object. Anything that does
	 * not decode to an array/object is replaced with an empty object so the
	 * editor never receives malformed state.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string Normalised JSON string.
	 */
	public static function sanitize_content( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '{}';
		}

		$decoded = json_decode( $value, true );

		if ( ! is_array( $decoded ) ) {
			return '{}';
		}

		$encoded = wp_json_encode( $decoded );

		return false === $encoded ? '{}' : $encoded;
	}

	/**
	 * Meta auth: may the user edit this note?
	 *
	 * @param bool   $allowed   Whether the user can add/edit the meta. Unused.
	 * @param string $meta_key  Meta key. Unused.
	 * @param int    $object_id Post ID.
	 * @return bool
	 */
	public static function auth_edit( $allowed, $meta_key, $object_id ) {
		return current_user_can( 'edit_post', (int) $object_id );
	}

	/**
	 * Meta auth: only the note's author may flip the public flag.
	 *
	 * Publishing a note to the world is deliberately an author-level decision and
	 * is never delegated to users with `edit_others_easynote_notes`.
	 *
	 * @param bool   $allowed   Whether the user can add/edit the meta. Unused.
	 * @param string $meta_key  Meta key. Unused.
	 * @param int    $object_id Post ID.
	 * @param int    $user_id   Acting user ID.
	 * @return bool
	 */
	public static function auth_author_only( $allowed, $meta_key, $object_id, $user_id ) {
		$post = get_post( (int) $object_id );

		if ( ! $post ) {
			return false;
		}

		return (int) $post->post_author === (int) $user_id
			&& user_can( (int) $user_id, 'edit_post', (int) $object_id );
	}
}
