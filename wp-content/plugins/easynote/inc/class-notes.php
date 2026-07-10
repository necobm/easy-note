<?php
/**
 * Note (post) and category (term) data-model helpers.
 *
 * @package EasyNote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps the Notion-like model onto built-in WordPress objects:
 *
 * - Categories (hierarchical `category` terms) are the tree nodes; a term's
 *   `description` holds its body text.
 * - Posts are the leaf notes, filed under the category they are assigned to.
 * - Notes with no real category surface under a virtual "Inbox" (the built-in
 *   default `Uncategorized` term is treated as the Inbox, never as a node).
 */
final class EasyNote_Notes {

	/**
	 * Register hooks (create-in-category behaviour).
	 */
	public function register() {
		add_action( 'load-post-new.php', array( $this, 'maybe_prefill_category' ) );
	}

	/**
	 * Term id of the built-in default category ("Uncategorized").
	 *
	 * @return int
	 */
	public static function default_category_id() {
		return (int) get_option( 'default_category' );
	}

	/**
	 * Post statuses that count as live notes (everything except trashed).
	 *
	 * @return string[]
	 */
	public static function note_statuses() {
		return array( 'publish', 'future', 'draft', 'pending', 'private' );
	}

	/**
	 * Get child categories of a parent term, alphabetically.
	 *
	 * The default category is excluded from the tree (it is the Inbox).
	 *
	 * @param int $parent_id Parent term id (0 for roots).
	 * @return WP_Term[]
	 */
	public static function get_child_categories( $parent_id = 0 ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'parent'     => (int) $parent_id,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'exclude'    => array( self::default_category_id() ),
			)
		);

		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Get the notes (posts) filed directly under a category, alphabetically.
	 *
	 * @param int $term_id Category term id.
	 * @return WP_Post[]
	 */
	public static function get_notes_in_category( $term_id ) {
		return get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => self::note_statuses(),
				'numberposts'      => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
				'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy'         => 'category',
						'field'            => 'term_id',
						'terms'            => (int) $term_id,
						'include_children' => false,
					),
				),
			)
		);
	}

	/**
	 * Get the Inbox notes: posts whose only category is the default one.
	 *
	 * @return WP_Post[]
	 */
	public static function get_inbox_notes() {
		return get_posts(
			array(
				'post_type'    => 'post',
				'post_status'  => self::note_statuses(),
				'numberposts'  => -1,
				'orderby'      => 'title',
				'order'        => 'ASC',
				'category'     => self::default_category_id(),
			)
		);
	}

	/**
	 * Category id to file the new note under for the current request.
	 *
	 * @var int
	 */
	private $prefill_category = 0;

	/**
	 * When "Add note" is launched from a category page, remember the target
	 * category (for this request only) and assign it to the auto-draft that the
	 * new-post screen creates. The editor then loads with that category
	 * selected, so it is preserved when the note is saved — this works for both
	 * the block editor (which persists terms at REST save time) and the classic
	 * editor. The site-wide default category option is never changed.
	 */
	public function maybe_prefill_category() {
		if ( empty( $_GET['easynote_cat'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill of a GET param, no state change.
			return;
		}

		$cat = absint( wp_unslash( $_GET['easynote_cat'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $cat || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		if ( ! term_exists( $cat, 'category' ) ) {
			return;
		}

		$this->prefill_category = $cat;
		add_action( 'wp_insert_post', array( $this, 'assign_prefill_category' ), 10, 3 );
	}

	/**
	 * Assign the remembered category to the freshly created auto-draft.
	 *
	 * @param int     $post_id New post id.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update (vs. a new insert).
	 */
	public function assign_prefill_category( $post_id, $post, $update ) {
		if ( $update || ! $this->prefill_category || 'post' !== $post->post_type ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		wp_set_post_terms( $post_id, array( $this->prefill_category ), 'category', false );

		// Assign only to the first (auto-draft) insert of this request.
		$this->prefill_category = 0;
	}
}
