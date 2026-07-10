<?php
/**
 * The Notion-like category (node) screen and its form handlers.
 *
 * @package EasyNote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the single EasyNote admin screen:
 *
 * - node 0 (home): the workspace root — top-level categories plus the Inbox.
 * - node N: category N — its title, description body, and a clickable list of
 *   child categories and notes, with affordances to add/edit.
 *
 * All state changes (create sub-category, edit category) go through
 * admin-post.php handlers guarded by a nonce and a capability check.
 */
final class EasyNote_Category_Page {

	const ADD_ACTION  = 'easynote_add_category';
	const EDIT_ACTION = 'easynote_edit_category';

	/**
	 * Register the form handlers.
	 */
	public function register() {
		add_action( 'admin_post_' . self::ADD_ACTION, array( $this, 'handle_add_category' ) );
		add_action( 'admin_post_' . self::EDIT_ACTION, array( $this, 'handle_edit_category' ) );
	}

	/**
	 * URL of the EasyNote screen for a given node.
	 *
	 * @param int $node Category term id, or 0 for home.
	 * @return string
	 */
	public static function url( $node = 0 ) {
		$args = array( 'page' => EASYNOTE_PAGE );
		if ( $node ) {
			$args['node'] = (int) $node;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * URL to create a new note, optionally pre-filed under a category.
	 *
	 * @param int $node Category term id, or 0 for Inbox.
	 * @return string
	 */
	public static function new_note_url( $node = 0 ) {
		$args = array( 'post_type' => 'post' );
		if ( $node ) {
			$args['easynote_cat'] = (int) $node;
		}
		return add_query_arg( $args, admin_url( 'post-new.php' ) );
	}

	/**
	 * Render the screen for the requested node.
	 *
	 * @param int $node Category term id, or 0 for home.
	 */
	public function render( $node ) {
		if ( ! current_user_can( EasyNote_Roles::NOTE_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access EasyNote.', 'easynote' ) );
		}

		$node = (int) $node;
		$term = $node ? get_term( $node, 'category' ) : null;

		echo '<div class="wrap easynote-page">';

		if ( $node && ( ! $term || is_wp_error( $term ) ) ) {
			echo '<h1>' . esc_html__( 'Not found', 'easynote' ) . '</h1>';
			echo '<p>' . esc_html__( 'That note page no longer exists.', 'easynote' ) . '</p>';
			echo '<p><a href="' . esc_url( self::url() ) . '">' . esc_html__( '← Back to workspace', 'easynote' ) . '</a></p>';
			echo '</div>';
			return;
		}

		$this->render_notices();

		if ( $term ) {
			$this->render_category( $term );
		} else {
			$this->render_home();
		}

		echo '</div>';
	}

	/**
	 * Render the workspace root.
	 */
	private function render_home() {
		echo '<h1 class="easynote-title">' . esc_html( get_bloginfo( 'name' ) ) . '</h1>';
		echo '<p class="easynote-subtitle">' . esc_html__( 'Your workspace', 'easynote' ) . '</p>';

		$this->render_add_bar( 0 );

		$categories = EasyNote_Notes::get_child_categories( 0 );
		$inbox      = EasyNote_Notes::get_inbox_notes();

		echo '<ul class="easynote-children">';
		foreach ( $categories as $category ) {
			$this->render_child_category( $category );
		}
		// Inbox pseudo-node.
		echo '<li class="easynote-child easynote-child--inbox">';
		echo '<span class="easynote-child__icon dashicons dashicons-email-alt"></span>';
		echo '<span class="easynote-child__label">' . esc_html__( 'Inbox', 'easynote' ) . '</span>';
		echo ' <span class="easynote-child__count">' . count( $inbox ) . '</span>';
		echo '</li>';
		foreach ( $inbox as $note ) {
			$this->render_child_note( $note );
		}
		echo '</ul>';

		if ( empty( $categories ) && empty( $inbox ) ) {
			$this->render_empty_state();
		}
	}

	/**
	 * Render a single category node with its body and children.
	 *
	 * @param WP_Term $term Category term.
	 */
	private function render_category( WP_Term $term ) {
		$this->render_breadcrumb( $term );

		echo '<h1 class="easynote-title">' . esc_html( $term->name ) . '</h1>';

		if ( '' !== trim( (string) $term->description ) ) {
			echo '<div class="easynote-body">' . wp_kses_post( wpautop( esc_html( $term->description ) ) ) . '</div>';
		}

		$this->render_add_bar( $term->term_id );
		$this->render_edit_form( $term );

		$children = EasyNote_Notes::get_child_categories( $term->term_id );
		$notes    = EasyNote_Notes::get_notes_in_category( $term->term_id );

		if ( empty( $children ) && empty( $notes ) ) {
			$this->render_empty_state();
			return;
		}

		echo '<ul class="easynote-children">';
		foreach ( $children as $child ) {
			$this->render_child_category( $child );
		}
		foreach ( $notes as $note ) {
			$this->render_child_note( $note );
		}
		echo '</ul>';
	}

	/**
	 * Render the ancestor breadcrumb for a category.
	 *
	 * @param WP_Term $term Category term.
	 */
	private function render_breadcrumb( WP_Term $term ) {
		$crumbs   = array();
		$crumbs[] = '<a href="' . esc_url( self::url() ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</a>';

		$ancestors = array_reverse( get_ancestors( $term->term_id, 'category' ) );
		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'category' );
			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$crumbs[] = '<a href="' . esc_url( self::url( $ancestor_id ) ) . '">' . esc_html( $ancestor->name ) . '</a>';
			}
		}

		echo '<nav class="easynote-breadcrumb">' . wp_kses_post( implode( ' <span class="sep">/</span> ', $crumbs ) ) . '</nav>';
	}

	/**
	 * Render the "add sub-page / add note" action bar.
	 *
	 * @param int $node Current category id (0 for home).
	 */
	private function render_add_bar( $node ) {
		echo '<div class="easynote-actions">';

		if ( current_user_can( 'manage_categories' ) ) {
			echo '<details class="easynote-add-sub">';
			echo '<summary class="button">' . esc_html__( '+ Add sub-page', 'easynote' ) . '</summary>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="easynote-form">';
			wp_nonce_field( self::ADD_ACTION );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ADD_ACTION ) . '">';
			echo '<input type="hidden" name="parent" value="' . esc_attr( (string) $node ) . '">';
			echo '<input type="text" name="cat_name" required placeholder="' . esc_attr__( 'Sub-page title', 'easynote' ) . '" class="regular-text">';
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Create', 'easynote' ) . '</button>';
			echo '</form>';
			echo '</details>';
		}

		echo '<a class="button button-primary easynote-add-note" href="' . esc_url( self::new_note_url( $node ) ) . '">' . esc_html__( '+ Add note', 'easynote' ) . '</a>';

		echo '</div>';
	}

	/**
	 * Render the collapsible "edit this page" form for a category.
	 *
	 * @param WP_Term $term Category term.
	 */
	private function render_edit_form( WP_Term $term ) {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		echo '<details class="easynote-edit">';
		echo '<summary class="button-link">' . esc_html__( 'Edit page title & text', 'easynote' ) . '</summary>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="easynote-form easynote-form--stack">';
		wp_nonce_field( self::EDIT_ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::EDIT_ACTION ) . '">';
		echo '<input type="hidden" name="term_id" value="' . esc_attr( (string) $term->term_id ) . '">';
		echo '<p><label>' . esc_html__( 'Title', 'easynote' ) . '<br>';
		echo '<input type="text" name="cat_name" required value="' . esc_attr( $term->name ) . '" class="regular-text"></label></p>';
		echo '<p><label>' . esc_html__( 'Text', 'easynote' ) . '<br>';
		echo '<textarea name="cat_description" rows="5" class="large-text">' . esc_textarea( $term->description ) . '</textarea></label></p>';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Save', 'easynote' ) . '</button>';
		echo '</form>';
		echo '</details>';
	}

	/**
	 * Render a clickable child-category row.
	 *
	 * @param WP_Term $term Category term.
	 */
	private function render_child_category( WP_Term $term ) {
		echo '<li class="easynote-child easynote-child--category">';
		echo '<a href="' . esc_url( self::url( $term->term_id ) ) . '">';
		echo '<span class="easynote-child__icon dashicons dashicons-category"></span>';
		echo '<span class="easynote-child__label">' . esc_html( $term->name ) . '</span>';
		echo '</a>';
		echo '</li>';
	}

	/**
	 * Render a clickable child-note row with a trash action.
	 *
	 * @param WP_Post $note Post object.
	 */
	private function render_child_note( WP_Post $note ) {
		$title  = get_the_title( $note );
		$title  = '' !== $title ? $title : __( '(untitled note)', 'easynote' );
		$edit   = get_edit_post_link( $note->ID );
		$delete = get_delete_post_link( $note->ID ); // Defaults to Trash (recoverable).

		echo '<li class="easynote-child easynote-child--note">';
		if ( $edit ) {
			echo '<a href="' . esc_url( $edit ) . '">';
			echo '<span class="easynote-child__icon dashicons dashicons-media-document"></span>';
			echo '<span class="easynote-child__label">' . esc_html( $title ) . '</span>';
			echo '</a>';
		} else {
			echo '<span class="easynote-child__icon dashicons dashicons-media-document"></span>';
			echo '<span class="easynote-child__label">' . esc_html( $title ) . '</span>';
		}
		if ( $delete && current_user_can( 'delete_post', $note->ID ) ) {
			echo ' <a class="easynote-child__trash" href="' . esc_url( $delete ) . '" aria-label="' . esc_attr__( 'Move note to Trash', 'easynote' ) . '">' . esc_html__( 'Trash', 'easynote' ) . '</a>';
		}
		echo '</li>';
	}

	/**
	 * Render a friendly empty state.
	 */
	private function render_empty_state() {
		echo '<div class="easynote-empty">';
		echo '<p>' . esc_html__( 'Nothing here yet.', 'easynote' ) . '</p>';
		echo '<p>' . esc_html__( 'Use “Add note” to create your first note, or “Add sub-page” to nest another page.', 'easynote' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render success/error notices from a completed form action.
	 */
	private function render_notices() {
		if ( empty( $_GET['easynote_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag after a redirect.
			return;
		}

		$msg = sanitize_key( wp_unslash( $_GET['easynote_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$messages = array(
			'created' => __( 'Sub-page created.', 'easynote' ),
			'updated' => __( 'Page saved.', 'easynote' ),
			'error'   => __( 'Something went wrong. Please try again.', 'easynote' ),
		);

		if ( isset( $messages[ $msg ] ) ) {
			$class = ( 'error' === $msg ) ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $messages[ $msg ] ) . '</p></div>';
		}
	}

	/**
	 * Handle creation of a sub-category.
	 */
	public function handle_add_category() {
		check_admin_referer( self::ADD_ACTION );

		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_die( esc_html__( 'You are not allowed to create pages.', 'easynote' ) );
		}

		$parent = isset( $_POST['parent'] ) ? absint( wp_unslash( $_POST['parent'] ) ) : 0;
		$name   = isset( $_POST['cat_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cat_name'] ) ) : '';

		$msg = 'error';
		if ( '' !== $name ) {
			$result = wp_insert_term( $name, 'category', array( 'parent' => $parent ) );
			if ( ! is_wp_error( $result ) ) {
				$msg = 'created';
			}
		}

		$this->redirect_to_node( $parent, $msg );
	}

	/**
	 * Handle editing a category's title and body text.
	 */
	public function handle_edit_category() {
		check_admin_referer( self::EDIT_ACTION );

		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_die( esc_html__( 'You are not allowed to edit pages.', 'easynote' ) );
		}

		$term_id     = isset( $_POST['term_id'] ) ? absint( wp_unslash( $_POST['term_id'] ) ) : 0;
		$name        = isset( $_POST['cat_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cat_name'] ) ) : '';
		$description = isset( $_POST['cat_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cat_description'] ) ) : '';

		$msg = 'error';
		if ( $term_id && '' !== $name && term_exists( $term_id, 'category' ) ) {
			$result = wp_update_term(
				$term_id,
				'category',
				array(
					'name'        => $name,
					'description' => $description,
				)
			);
			if ( ! is_wp_error( $result ) ) {
				$msg = 'updated';
			}
		}

		$this->redirect_to_node( $term_id, $msg );
	}

	/**
	 * Redirect back to a node page with a status message, then exit.
	 *
	 * @param int    $node Category id (0 for home).
	 * @param string $msg  Message key.
	 */
	private function redirect_to_node( $node, $msg ) {
		$url = add_query_arg( 'easynote_msg', $msg, self::url( $node ) );
		wp_safe_redirect( $url );
		exit;
	}
}
