<?php
/**
 * Unauthenticated public-note read endpoint.
 *
 * @package EasyNote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves a single public note by slug to anyone, with no authentication.
 *
 * Kept deliberately separate from the authenticated REST surface so the "never
 * leak a private note" guarantee lives in one small, auditable place. A note is
 * only ever returned when it is explicitly flagged public and in a live status;
 * every other case returns a 404 (not 403) so the response never confirms
 * whether a private note exists.
 */
final class EasyNote_REST_Public {

	/**
	 * Wire up hooks.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the public route.
	 */
	public function register_routes() {
		register_rest_route(
			EasyNote_REST_API::NAMESPACE,
			'/public-notes/(?P<slug>[a-zA-Z0-9-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_public_note' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'slug' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);
	}

	/**
	 * Return a public note's safe fields, or 404.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_public_note( $request ) {
		$slug = sanitize_title( (string) $request['slug'] );
		$post = get_page_by_path( $slug, OBJECT, EasyNote_Post_Type::POST_TYPE );

		if ( ! $post
			|| 'publish' !== $post->post_status
			|| true !== (bool) get_post_meta( $post->ID, EasyNote_Post_Type::PUBLIC_META, true )
		) {
			return new WP_Error(
				'easynote_not_found',
				__( 'Note not found.', 'easynote' ),
				array( 'status' => 404 )
			);
		}

		$content = get_post_meta( $post->ID, EasyNote_Post_Type::CONTENT_META, true );

		return rest_ensure_response(
			array(
				'id'       => (int) $post->ID,
				'slug'     => $post->post_name,
				'title'    => get_the_title( $post ),
				'content'  => is_string( $content ) && '' !== $content ? $content : '{}',
				'modified' => mysql2date( 'c', $post->post_modified_gmt, false ),
			)
		);
	}
}
