<?php
/**
 * The nested, collapsible sidebar tree.
 *
 * @package EasyNote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the category -> note tree and renders it (server-side, escaped) into
 * the admin footer. A small vanilla script relocates it under the EasyNote
 * menu item in the admin sidebar and wires expand/collapse + active-node
 * highlighting. Because the admin sidebar is present on every admin screen,
 * the assets load on all admin pages for users who can edit notes.
 */
final class EasyNote_Sidebar_Tree {

	/** Hard depth cap to avoid pathological recursion. */
	const MAX_DEPTH = 20;

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( $this, 'render_source' ) );
	}

	/**
	 * Enqueue the tree/page CSS and JS for note-capable users.
	 */
	public function enqueue_assets() {
		if ( ! current_user_can( EasyNote_Roles::NOTE_CAP ) ) {
			return;
		}

		wp_enqueue_style(
			'easynote-admin',
			EASYNOTE_URL . 'assets/css/easynote-admin.css',
			array(),
			EASYNOTE_VERSION
		);

		wp_enqueue_script(
			'easynote-admin',
			EASYNOTE_URL . 'assets/js/easynote-admin.js',
			array(),
			EASYNOTE_VERSION,
			true
		);
	}

	/**
	 * Output the tree markup (hidden); JS moves it into the sidebar.
	 */
	public function render_source() {
		if ( ! current_user_can( EasyNote_Roles::NOTE_CAP ) ) {
			return;
		}

		echo '<div id="easynote-tree-source" hidden>';
		echo '<ul class="easynote-tree" role="tree">';

		foreach ( EasyNote_Notes::get_child_categories( 0 ) as $category ) {
			$this->render_category_branch( $category, 1 );
		}

		$this->render_inbox_branch();

		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Recursively render a category branch.
	 *
	 * @param WP_Term $term  Category term.
	 * @param int     $depth Current depth.
	 */
	private function render_category_branch( WP_Term $term, $depth ) {
		$children = ( $depth < self::MAX_DEPTH ) ? EasyNote_Notes::get_child_categories( $term->term_id ) : array();
		$notes    = ( $depth < self::MAX_DEPTH ) ? EasyNote_Notes::get_notes_in_category( $term->term_id ) : array();
		$has_kids = ! empty( $children ) || ! empty( $notes );

		echo '<li class="easynote-node easynote-node--category' . ( $has_kids ? ' has-children' : '' ) . '" role="treeitem">';
		echo '<span class="easynote-row">';
		if ( $has_kids ) {
			echo '<button type="button" class="easynote-toggle" aria-expanded="false"><span class="dashicons dashicons-arrow-right"></span></button>';
		} else {
			echo '<span class="easynote-toggle easynote-toggle--empty"></span>';
		}
		echo '<a class="easynote-link" data-node="' . esc_attr( (string) $term->term_id ) . '" href="' . esc_url( EasyNote_Category_Page::url( $term->term_id ) ) . '">';
		echo '<span class="dashicons dashicons-category"></span> ' . esc_html( $term->name );
		echo '</a>';
		echo '</span>';

		if ( $has_kids ) {
			echo '<ul class="easynote-children" role="group">';
			foreach ( $children as $child ) {
				$this->render_category_branch( $child, $depth + 1 );
			}
			foreach ( $notes as $note ) {
				$this->render_note_leaf( $note );
			}
			echo '</ul>';
		}

		echo '</li>';
	}

	/**
	 * Render a note leaf.
	 *
	 * @param WP_Post $note Post object.
	 */
	private function render_note_leaf( WP_Post $note ) {
		$title = get_the_title( $note );
		$title = '' !== $title ? $title : __( '(untitled note)', 'easynote' );
		$edit  = get_edit_post_link( $note->ID );

		echo '<li class="easynote-node easynote-node--note" role="treeitem">';
		echo '<span class="easynote-row">';
		echo '<span class="easynote-toggle easynote-toggle--empty"></span>';
		echo '<a class="easynote-link" data-post="' . esc_attr( (string) $note->ID ) . '" href="' . esc_url( $edit ? $edit : '#' ) . '">';
		echo '<span class="dashicons dashicons-media-document"></span> ' . esc_html( $title );
		echo '</a>';
		echo '</span>';
		echo '</li>';
	}

	/**
	 * Render the virtual Inbox branch (uncategorized notes).
	 */
	private function render_inbox_branch() {
		$notes    = EasyNote_Notes::get_inbox_notes();
		$has_kids = ! empty( $notes );

		echo '<li class="easynote-node easynote-node--inbox' . ( $has_kids ? ' has-children' : '' ) . '" role="treeitem">';
		echo '<span class="easynote-row">';
		if ( $has_kids ) {
			echo '<button type="button" class="easynote-toggle" aria-expanded="false"><span class="dashicons dashicons-arrow-right"></span></button>';
		} else {
			echo '<span class="easynote-toggle easynote-toggle--empty"></span>';
		}
		echo '<a class="easynote-link" href="' . esc_url( EasyNote_Category_Page::url( 0 ) ) . '">';
		echo '<span class="dashicons dashicons-email-alt"></span> ' . esc_html__( 'Inbox', 'easynote' );
		echo '</a>';
		echo '</span>';

		if ( $has_kids ) {
			echo '<ul class="easynote-children" role="group">';
			foreach ( $notes as $note ) {
				$this->render_note_leaf( $note );
			}
			echo '</ul>';
		}

		echo '</li>';
	}
}
