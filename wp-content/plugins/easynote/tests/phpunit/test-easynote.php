<?php
/**
 * PHPUnit tests for EasyNote's headless logic and REST surface.
 *
 * @package EasyNote
 */

/**
 * @covers EasyNote_Post_Type
 * @covers EasyNote_Taxonomy
 * @covers EasyNote_Notes
 * @covers EasyNote_Roles
 * @covers EasyNote_Activator
 * @covers EasyNote_REST_API
 * @covers EasyNote_REST_Public
 */
class Test_EasyNote extends WP_UnitTestCase {

	/**
	 * Rebuild the REST server so the plugin's routes are registered per test.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	/**
	 * The post type and taxonomy are registered and exposed to REST.
	 */
	public function test_post_type_and_taxonomy_registered() {
		$this->assertTrue( post_type_exists( EasyNote_Post_Type::POST_TYPE ) );
		$this->assertTrue( taxonomy_exists( EasyNote_Taxonomy::TAXONOMY ) );

		$pt = get_post_type_object( EasyNote_Post_Type::POST_TYPE );
		$this->assertTrue( $pt->show_in_rest );
		$this->assertSame( 'notes', $pt->rest_base );
	}

	/**
	 * Activation grants note caps to administrators and records the version.
	 */
	public function test_activation_grants_caps_and_records_version() {
		EasyNote_Activator::activate();
		EasyNote_Activator::activate(); // Idempotent.

		$this->assertSame( EASYNOTE_VERSION, get_option( 'easynote_version' ) );
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'edit_others_easynote_notes' ) );
	}

	/**
	 * The content meta sanitizer normalises JSON and rejects junk.
	 */
	public function test_content_sanitizer() {
		$this->assertSame( '{"a":1}', EasyNote_Post_Type::sanitize_content( '{ "a" : 1 }' ) );
		$this->assertSame( '{}', EasyNote_Post_Type::sanitize_content( 'not json' ) );
		$this->assertSame( '{}', EasyNote_Post_Type::sanitize_content( '' ) );
	}

	/**
	 * The tree groups notes under folders and surfaces loose notes in the Inbox.
	 */
	public function test_build_tree_structure() {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		$folder = self::factory()->term->create( array( 'taxonomy' => EasyNote_Taxonomy::TAXONOMY, 'name' => 'Work' ) );
		$filed  = $this->make_note( $user, 'Filed', $folder );
		$loose  = $this->make_note( $user, 'Loose' );

		$tree = EasyNote_Notes::build_tree( $user, false );

		$folder_node = null;
		foreach ( $tree['folders'] as $node ) {
			if ( $node['id'] === $folder ) {
				$folder_node = $node;
			}
		}

		$this->assertNotNull( $folder_node );
		$this->assertContains( $filed, wp_list_pluck( $folder_node['notes'], 'id' ) );
		$this->assertContains( $loose, wp_list_pluck( $tree['inbox'], 'id' ) );
		$this->assertNotContains( $filed, wp_list_pluck( $tree['inbox'], 'id' ) );
	}

	/**
	 * A user without edit_others sees only their own notes in the tree.
	 */
	public function test_ownership_scoping() {
		$owner  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$other  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->make_note( $owner, 'Owner note' );

		wp_set_current_user( $other );
		$tree  = EasyNote_Notes::build_tree( $other, false );
		$count = count( $tree['inbox'] );
		foreach ( $tree['folders'] as $node ) {
			$count += count( $node['notes'] );
		}

		$this->assertSame( 0, $count );
	}

	/**
	 * The public endpoint hides private notes (404) and serves shared ones (200).
	 */
	public function test_public_endpoint_visibility() {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );
		$note = $this->make_note( $user, 'Shareable' );
		$slug = get_post_field( 'post_name', $note );

		$res = rest_do_request( new WP_REST_Request( 'GET', "/easynote/v1/public-notes/{$slug}" ) );
		$this->assertSame( 404, $res->get_status() );

		update_post_meta( $note, EasyNote_Post_Type::PUBLIC_META, true );

		$res = rest_do_request( new WP_REST_Request( 'GET', "/easynote/v1/public-notes/{$slug}" ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertArrayNotHasKey( 'author', $res->get_data() );
	}

	/**
	 * Toggling public publishes the note and can set its slug.
	 */
	public function test_toggle_public_publishes() {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );
		$note = $this->make_note( $user, 'Draft note' );
		wp_update_post( array( 'ID' => $note, 'post_status' => 'draft' ) );

		$req = new WP_REST_Request( 'POST', "/easynote/v1/notes/{$note}/public" );
		$req->set_body_params( array( 'public' => true, 'slug' => 'my-share' ) );
		$res = rest_do_request( $req );

		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 'publish', get_post_status( $note ) );
		$this->assertSame( 'my-share', get_post_field( 'post_name', $note ) );
	}

	/**
	 * Only the note's author may flip the public flag.
	 */
	public function test_toggle_public_is_author_only() {
		$author = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$other  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $author );
		$note = $this->make_note( $author, 'Mine' );

		wp_set_current_user( $other );
		$req = new WP_REST_Request( 'POST', "/easynote/v1/notes/{$note}/public" );
		$req->set_body_params( array( 'public' => true ) );
		$res = rest_do_request( $req );

		$this->assertSame( 403, $res->get_status() );
	}

	/**
	 * Moving a note updates its folder assignment.
	 */
	public function test_move_note() {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );
		$folder = self::factory()->term->create( array( 'taxonomy' => EasyNote_Taxonomy::TAXONOMY ) );
		$note   = $this->make_note( $user, 'Movable' );

		$req = new WP_REST_Request( 'POST', "/easynote/v1/notes/{$note}/move" );
		$req->set_body_params( array( 'folder_id' => $folder ) );
		$res = rest_do_request( $req );

		$this->assertSame( 200, $res->get_status() );
		$this->assertContains( $folder, wp_get_post_terms( $note, EasyNote_Taxonomy::TAXONOMY, array( 'fields' => 'ids' ) ) );
	}

	/**
	 * Create a note owned by a user, optionally filed under a folder.
	 *
	 * @param int    $author Author id.
	 * @param string $title  Title.
	 * @param int    $folder Folder term id, or 0.
	 * @return int Note id.
	 */
	private function make_note( $author, $title, $folder = 0 ) {
		$note = self::factory()->post->create(
			array(
				'post_type'   => EasyNote_Post_Type::POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_author' => $author,
			)
		);
		if ( $folder ) {
			wp_set_post_terms( $note, array( $folder ), EasyNote_Taxonomy::TAXONOMY, false );
		}
		return $note;
	}
}
