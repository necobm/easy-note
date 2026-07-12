<?php
/**
 * Note and folder data-model helpers.
 *
 * @package EasyNote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query helpers over the `easynote_note` post type and `easynote_folder`
 * taxonomy, plus the workspace tree builder consumed by the REST layer.
 *
 * Notes are owned per-user via `post_author`; helpers accept an optional author
 * scope so the REST layer can restrict listings to the current user. Folders are
 * shared across the workspace. Notes filed under no folder surface in a virtual
 * "Inbox" node.
 */
final class EasyNote_Notes {

	/**
	 * No runtime hooks needed under the headless model.
	 */
	public function register() {}

	/**
	 * Post statuses that count as live notes (everything except trashed).
	 *
	 * @return string[]
	 */
	public static function note_statuses() {
		return array( 'publish', 'future', 'draft', 'pending', 'private' );
	}

	/**
	 * Get every folder term, ordered by name.
	 *
	 * @return WP_Term[]
	 */
	public static function get_folders() {
		$terms = get_terms(
			array(
				'taxonomy'   => EasyNote_Taxonomy::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Get notes, optionally scoped to a single author.
	 *
	 * @param int $author_id Author to scope to, or 0 for all authors.
	 * @return WP_Post[]
	 */
	public static function get_notes( $author_id = 0 ) {
		$args = array(
			'post_type'   => EasyNote_Post_Type::POST_TYPE,
			'post_status' => self::note_statuses(),
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		);

		if ( $author_id > 0 ) {
			$args['author'] = (int) $author_id;
		}

		return get_posts( $args );
	}

	/**
	 * Build the full folder + note tree for the app sidebar.
	 *
	 * Returns a nested structure of folder nodes (each with `children` folders and
	 * `notes`) plus a synthetic `inbox` list for notes with no folder. Notes are
	 * scoped to `$author_id` unless `$include_others` is true.
	 *
	 * @param int  $author_id      Author to scope notes to.
	 * @param bool $include_others Whether to include other users' notes.
	 * @return array{folders:array,inbox:array}
	 */
	public static function build_tree( $author_id, $include_others = false ) {
		$nodes = array();

		foreach ( self::get_folders() as $term ) {
			$nodes[ $term->term_id ] = array(
				'id'       => (int) $term->term_id,
				'name'     => $term->name,
				'slug'     => $term->slug,
				'parent'   => (int) $term->parent,
				'children' => array(),
				'notes'    => array(),
			);
		}

		$scope = $include_others ? 0 : (int) $author_id;
		$inbox = array();

		foreach ( self::get_notes( $scope ) as $post ) {
			$item       = self::note_summary( $post );
			$folder_ids = wp_get_post_terms( $post->ID, EasyNote_Taxonomy::TAXONOMY, array( 'fields' => 'ids' ) );

			if ( is_wp_error( $folder_ids ) || empty( $folder_ids ) ) {
				$inbox[] = $item;
				continue;
			}

			foreach ( $folder_ids as $folder_id ) {
				if ( isset( $nodes[ $folder_id ] ) ) {
					$nodes[ $folder_id ]['notes'][] = $item;
				}
			}
		}

		// Assemble the hierarchy: attach each node to its parent, collect roots.
		$roots = array();

		foreach ( $nodes as $id => &$node ) {
			if ( $node['parent'] && isset( $nodes[ $node['parent'] ] ) ) {
				$nodes[ $node['parent'] ]['children'][] = &$node;
			} else {
				$roots[] = &$node;
			}
		}
		unset( $node );

		return array(
			'folders' => $roots,
			'inbox'   => $inbox,
		);
	}

	/**
	 * Minimal note representation for tree/list responses.
	 *
	 * @param WP_Post $post Note post.
	 * @return array{id:int,title:string,slug:string,author:int,modified:string}
	 */
	public static function note_summary( $post ) {
		return array(
			'id'       => (int) $post->ID,
			'title'    => $post->post_title,
			'slug'     => $post->post_name,
			'author'   => (int) $post->post_author,
			'modified' => mysql2date( 'c', $post->post_modified_gmt, false ),
		);
	}
}
