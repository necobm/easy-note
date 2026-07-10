<?php
/**
 * PHPUnit tests for EasyNote's logic units.
 *
 * @package EasyNote
 */

/**
 * @covers EasyNote_Notes
 * @covers EasyNote_Roles
 * @covers EasyNote_Category_Page
 * @covers EasyNote_Activator
 */
class Test_EasyNote extends WP_UnitTestCase {

	/**
	 * Activation is idempotent and preserves the editor role.
	 */
	public function test_activation_is_idempotent() {
		EasyNote_Activator::activate();
		EasyNote_Activator::activate();

		$this->assertNotNull( get_role( 'editor' ) );
		$this->assertSame( EASYNOTE_VERSION, get_option( 'easynote_version' ) );
	}

	/**
	 * Root categories exclude the default (Inbox) term.
	 */
	public function test_root_categories_exclude_default() {
		$root = self::factory()->category->create( array( 'name' => 'Root A' ) );

		$ids = wp_list_pluck( EasyNote_Notes::get_child_categories( 0 ), 'term_id' );

		$this->assertContains( $root, $ids );
		$this->assertNotContains( EasyNote_Notes::default_category_id(), $ids );
	}

	/**
	 * Child categories are returned under their parent, alphabetically.
	 */
	public function test_child_categories_ordered() {
		$parent = self::factory()->category->create( array( 'name' => 'Parent' ) );
		self::factory()->category->create( array( 'name' => 'Zeta', 'parent' => $parent ) );
		self::factory()->category->create( array( 'name' => 'Alpha', 'parent' => $parent ) );

		$names = wp_list_pluck( EasyNote_Notes::get_child_categories( $parent ), 'name' );

		$this->assertSame( array( 'Alpha', 'Zeta' ), $names );
	}

	/**
	 * A note filed in a category is listed there and not in the Inbox.
	 */
	public function test_notes_in_category_vs_inbox() {
		$cat  = self::factory()->category->create( array( 'name' => 'Work' ) );
		$note = self::factory()->post->create( array( 'post_title' => 'Filed', 'post_category' => array( $cat ) ) );
		$loose = self::factory()->post->create( array( 'post_title' => 'Loose' ) );

		$in_cat = wp_list_pluck( EasyNote_Notes::get_notes_in_category( $cat ), 'ID' );
		$inbox  = wp_list_pluck( EasyNote_Notes::get_inbox_notes(), 'ID' );

		$this->assertContains( $note, $in_cat );
		$this->assertNotContains( $loose, $in_cat );
		$this->assertContains( $loose, $inbox );
		$this->assertNotContains( $note, $inbox );
	}

	/**
	 * Trashed notes drop out of the category listing.
	 */
	public function test_trashed_note_excluded() {
		$cat  = self::factory()->category->create();
		$note = self::factory()->post->create( array( 'post_category' => array( $cat ) ) );

		wp_trash_post( $note );

		$ids = wp_list_pluck( EasyNote_Notes::get_notes_in_category( $cat ), 'ID' );
		$this->assertNotContains( $note, $ids );
	}

	/**
	 * Create-in-category assigns the target category to a new auto-draft.
	 */
	public function test_create_in_category_assigns_term() {
		$user = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user );

		$cat   = self::factory()->category->create( array( 'name' => 'Target' ) );
		$notes = new EasyNote_Notes();

		// Simulate the new-post request pre-filed under $cat.
		$_GET['easynote_cat'] = $cat;
		$notes->maybe_prefill_category();

		$post_id = self::factory()->post->create( array( 'post_status' => 'auto-draft' ) );
		$notes->assign_prefill_category( $post_id, get_post( $post_id ), false );

		$this->assertContains( $cat, wp_get_post_categories( $post_id ) );
		unset( $_GET['easynote_cat'] );
	}

	/**
	 * Role helpers classify editors and admins correctly.
	 */
	public function test_role_classification() {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $editor );
		$this->assertTrue( EasyNote_Roles::is_restricted_user() );
		$this->assertFalse( EasyNote_Roles::is_site_admin() );

		wp_set_current_user( $admin );
		$this->assertFalse( EasyNote_Roles::is_restricted_user() );
		$this->assertTrue( EasyNote_Roles::is_site_admin() );
	}

	/**
	 * URL helpers build the documented routes.
	 */
	public function test_url_helpers() {
		$this->assertSame( admin_url( 'admin.php?page=easynote' ), EasyNote_Category_Page::url( 0 ) );
		$this->assertStringContainsString( 'node=42', EasyNote_Category_Page::url( 42 ) );
		$this->assertStringContainsString( 'easynote_cat=7', EasyNote_Category_Page::new_note_url( 7 ) );
	}

	/**
	 * The add-category handler rejects a request with no valid nonce.
	 */
	public function test_add_category_requires_nonce() {
		$user = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user );

		$page = new EasyNote_Category_Page();

		$_POST = array( 'cat_name' => 'No Nonce', 'parent' => 0 );

		$this->expectException( WPDieException::class );
		$page->handle_add_category();
	}
}
