<?php
/**
 * Authenticated REST endpoints for the headless app.
 *
 * @package EasyNote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `easynote/v1` action endpoints and the ownership scoping filter.
 *
 * Plain resource CRUD is handled for free by core's post/term controllers
 * (the CPT and taxonomy are `show_in_rest`). This class adds the endpoints that
 * don't map cleanly onto a field PATCH — the whole-tree read, moving a note
 * between folders, toggling public visibility, and reparenting a folder — plus a
 * query filter so users only ever list their own notes.
 */
final class EasyNote_REST_API {

	/** REST namespace for the custom action endpoints. */
	const NAMESPACE = 'easynote/v1';

	/**
	 * Wire up hooks.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_easynote_note_query', array( $this, 'scope_to_author' ), 10, 2 );
	}

	/**
	 * Restrict note listings to the current user unless they can manage others'.
	 *
	 * @param array           $args    WP_Query args built by the REST controller.
	 * @param WP_REST_Request $request The REST request. Unused.
	 * @return array
	 */
	public function scope_to_author( $args, $request ) {
		if ( ! EasyNote_Roles::can_manage_others_notes() ) {
			$args['author'] = get_current_user_id();
		}

		return $args;
	}

	/**
	 * Register the custom routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/tree',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_tree' ),
				'permission_callback' => array( $this, 'require_logged_in' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/notes/(?P<id>\d+)/move',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'move_note' ),
				'permission_callback' => array( $this, 'can_edit_note' ),
				'args'                => array(
					'folder_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'description'       => __( 'Target folder term id, or 0 for the Inbox.', 'easynote' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/notes/(?P<id>\d+)/public',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'toggle_public' ),
				'permission_callback' => array( $this, 'is_note_author' ),
				'args'                => array(
					'public' => array(
						'required'          => true,
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'slug'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/folders/(?P<id>\d+)/move',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'move_folder' ),
				'permission_callback' => array( $this, 'can_manage_folders' ),
				'args'                => array(
					'parent_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'description'       => __( 'New parent folder term id, or 0 for a root folder.', 'easynote' ),
					),
				),
			)
		);
	}

	/* --------------------------------------------------------------------- */
	/* Permission callbacks                                                   */
	/* --------------------------------------------------------------------- */

	/**
	 * @return bool
	 */
	public function require_logged_in() {
		return is_user_logged_in();
	}

	/**
	 * The user may edit the note identified by the `id` route param.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function can_edit_note( $request ) {
		$id = (int) $request['id'];

		if ( EasyNote_Post_Type::POST_TYPE !== get_post_type( $id ) ) {
			return self::not_found();
		}

		return current_user_can( 'edit_post', $id );
	}

	/**
	 * The user is the author of the note identified by the `id` route param.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function is_note_author( $request ) {
		$id   = (int) $request['id'];
		$post = get_post( $id );

		if ( ! $post || EasyNote_Post_Type::POST_TYPE !== $post->post_type ) {
			return self::not_found();
		}

		return current_user_can( 'edit_post', $id )
			&& (int) $post->post_author === get_current_user_id();
	}

	/**
	 * @return bool
	 */
	public function can_manage_folders() {
		return current_user_can( 'manage_categories' );
	}

	/* --------------------------------------------------------------------- */
	/* Route callbacks                                                        */
	/* --------------------------------------------------------------------- */

	/**
	 * Return the whole folder + note tree in one response.
	 *
	 * @param WP_REST_Request $request Request. Unused.
	 * @return WP_REST_Response
	 */
	public function get_tree( $request ) {
		$tree = EasyNote_Notes::build_tree(
			get_current_user_id(),
			EasyNote_Roles::can_manage_others_notes()
		);

		return rest_ensure_response( $tree );
	}

	/**
	 * Move a note into a folder (or the Inbox when folder_id is 0).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function move_note( $request ) {
		$id     = (int) $request['id'];
		$folder = (int) $request['folder_id'];

		if ( $folder > 0 && ! term_exists( $folder, EasyNote_Taxonomy::TAXONOMY ) ) {
			return new WP_Error(
				'easynote_folder_not_found',
				__( 'The target folder does not exist.', 'easynote' ),
				array( 'status' => 404 )
			);
		}

		$terms  = $folder > 0 ? array( $folder ) : array();
		$result = wp_set_post_terms( $id, $terms, EasyNote_Taxonomy::TAXONOMY, false );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'id' => $id, 'folder_id' => $folder ) );
	}

	/**
	 * Toggle a note's public visibility, optionally setting its public slug.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function toggle_public( $request ) {
		$id     = (int) $request['id'];
		$public = (bool) $request['public'];

		update_post_meta( $id, EasyNote_Post_Type::PUBLIC_META, $public );

		// A public note must be genuinely published so the public endpoint can
		// serve it; publish it now if it is still a draft/pending note.
		if ( $public && 'publish' !== get_post_status( $id ) ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				)
			);
		}

		$slug = $request['slug'];

		if ( $public && ! empty( $slug ) ) {
			$unique = wp_unique_post_slug( $slug, $id, 'publish', EasyNote_Post_Type::POST_TYPE, 0 );

			wp_update_post(
				array(
					'ID'        => $id,
					'post_name' => $unique,
				)
			);
		}

		return rest_ensure_response(
			array(
				'id'     => $id,
				'public' => $public,
				'slug'   => get_post_field( 'post_name', $id ),
			)
		);
	}

	/**
	 * Reparent a folder, guarding against cycles.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function move_folder( $request ) {
		$id     = (int) $request['id'];
		$parent = (int) $request['parent_id'];

		if ( ! term_exists( $id, EasyNote_Taxonomy::TAXONOMY ) ) {
			return self::not_found();
		}

		if ( $parent > 0 ) {
			if ( ! term_exists( $parent, EasyNote_Taxonomy::TAXONOMY ) ) {
				return new WP_Error(
					'easynote_folder_not_found',
					__( 'The target parent folder does not exist.', 'easynote' ),
					array( 'status' => 404 )
				);
			}

			if ( $parent === $id || self::is_descendant( $parent, $id ) ) {
				return new WP_Error(
					'easynote_invalid_parent',
					__( 'A folder cannot be moved into itself or one of its descendants.', 'easynote' ),
					array( 'status' => 400 )
				);
			}
		}

		$result = wp_update_term( $id, EasyNote_Taxonomy::TAXONOMY, array( 'parent' => $parent ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'id' => $id, 'parent_id' => $parent ) );
	}

	/* --------------------------------------------------------------------- */
	/* Helpers                                                                */
	/* --------------------------------------------------------------------- */

	/**
	 * Whether $maybe_child is $ancestor or nested beneath it.
	 *
	 * @param int $maybe_child Candidate descendant term id.
	 * @param int $ancestor    Ancestor term id.
	 * @return bool
	 */
	private static function is_descendant( $maybe_child, $ancestor ) {
		$ancestors = get_ancestors( $maybe_child, EasyNote_Taxonomy::TAXONOMY, 'taxonomy' );

		return in_array( $ancestor, array_map( 'intval', $ancestors ), true );
	}

	/**
	 * A generic 404 error.
	 *
	 * @return WP_Error
	 */
	private static function not_found() {
		return new WP_Error(
			'easynote_not_found',
			__( 'Note not found.', 'easynote' ),
			array( 'status' => 404 )
		);
	}
}
