<?php
/**
 * EasyNote WP-CLI smoke test (assertion-based).
 *
 * Exercises the headless plugin's logic and REST surface against a live
 * WordPress install and reports PASS/FAIL. This is the fallback verification
 * used when the full PHPUnit harness (svn/composer/phpunit + test DB) is not
 * available in the environment.
 *
 * Usage (inside the container):
 *   wp eval-file wp-content/plugins/easynote/tests/wpcli-smoke.php --allow-root
 *
 * It creates its own throwaway fixtures and removes them afterwards, so it is
 * safe to run repeatedly. Exit code is non-zero if any assertion fails.
 *
 * @package EasyNote
 */

// Use $GLOBALS explicitly: wp eval-file includes this file inside a method, so
// plain top-level variables are not in the global scope that en_assert writes.
$GLOBALS['en_pass'] = 0;
$GLOBALS['en_fail'] = 0;

/**
 * Assert a condition.
 *
 * @param string $label Description.
 * @param bool   $cond  Condition.
 */
function en_assert( $label, $cond ) {
	if ( $cond ) {
		++$GLOBALS['en_pass'];
		echo "  PASS  {$label}\n";
	} else {
		++$GLOBALS['en_fail'];
		echo "  FAIL  {$label}\n";
	}
}

/**
 * Dispatch an internal REST request.
 *
 * @param string $method HTTP method.
 * @param string $route  Route path.
 * @param array  $body   Body params.
 * @return WP_REST_Response
 */
function en_rest( $method, $route, $body = array() ) {
	$req = new WP_REST_Request( $method, $route );
	if ( $body ) {
		$req->set_body_params( $body );
	}
	return rest_do_request( $req );
}

echo "EasyNote WP-CLI smoke test\n";
echo "==========================\n";

$post_type = EasyNote_Post_Type::POST_TYPE;
$taxonomy  = EasyNote_Taxonomy::TAXONOMY;

// --- Classes load & registration -----------------------------------------
en_assert( 'plugin classes are loaded', class_exists( 'EasyNote_Post_Type' ) && class_exists( 'EasyNote_REST_API' ) && class_exists( 'EasyNote_Notes' ) );
en_assert( 'note post type is registered', post_type_exists( $post_type ) );
en_assert( 'folder taxonomy is registered', taxonomy_exists( $taxonomy ) );

// --- Activation idempotency ----------------------------------------------
EasyNote_Activator::activate();
EasyNote_Activator::activate(); // twice -> must not error or duplicate.
en_assert( 'activation records version option', EASYNOTE_VERSION === get_option( 'easynote_version' ) );
en_assert( 'administrators gain note caps', get_role( 'administrator' )->has_cap( 'edit_others_easynote_notes' ) );

// --- Fixtures -------------------------------------------------------------
$admin_id = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
wp_set_current_user( $admin_id );

$folder    = wp_insert_term( 'EN Smoke Folder ' . wp_generate_password( 6, false ), $taxonomy );
$folder_id = is_wp_error( $folder ) ? 0 : (int) $folder['term_id'];

$note_id = wp_insert_post(
	array(
		'post_title'  => 'EN Smoke Note',
		'post_status' => 'publish',
		'post_type'   => $post_type,
		'post_author' => $admin_id,
	)
);
wp_set_post_terms( $note_id, array( $folder_id ), $taxonomy, false );

$inbox_id = wp_insert_post(
	array(
		'post_title'  => 'EN Smoke Inbox',
		'post_status' => 'publish',
		'post_type'   => $post_type,
		'post_author' => $admin_id,
	)
);

// --- Meta sanitizer -------------------------------------------------------
en_assert( 'content sanitizer normalizes valid JSON', '{"a":1}' === EasyNote_Post_Type::sanitize_content( '{ "a" : 1 }' ) );
en_assert( 'content sanitizer rejects non-JSON to {}', '{}' === EasyNote_Post_Type::sanitize_content( 'not json' ) );

// --- Tree builder ---------------------------------------------------------
$tree        = EasyNote_Notes::build_tree( $admin_id, false );
$folder_node = null;
foreach ( $tree['folders'] as $f ) {
	if ( $f['id'] === $folder_id ) {
		$folder_node = $f;
	}
}
en_assert( 'tree contains our folder', null !== $folder_node );
en_assert( 'tree folder lists the filed note', $folder_node && in_array( $note_id, wp_list_pluck( $folder_node['notes'], 'id' ), true ) );
en_assert( 'tree inbox lists the loose note', in_array( $inbox_id, wp_list_pluck( $tree['inbox'], 'id' ), true ) );

// --- REST: tree -----------------------------------------------------------
$res = en_rest( 'GET', '/easynote/v1/tree' );
en_assert( 'REST tree returns 200', 200 === $res->get_status() );

// --- REST: move to inbox --------------------------------------------------
$res = en_rest( 'POST', "/easynote/v1/notes/{$note_id}/move", array( 'folder_id' => 0 ) );
en_assert( 'REST move returns 200', 200 === $res->get_status() );
en_assert( 'note has no folder after move', array() === wp_get_post_terms( $note_id, $taxonomy, array( 'fields' => 'ids' ) ) );

// --- REST: public endpoint before/after sharing ---------------------------
$slug = get_post_field( 'post_name', $note_id );
$res  = en_rest( 'GET', "/easynote/v1/public-notes/{$slug}" );
en_assert( 'private note is 404 on public endpoint', 404 === $res->get_status() );

$res = en_rest( 'POST', "/easynote/v1/notes/{$note_id}/public", array( 'public' => true ) );
en_assert( 'REST toggle-public returns 200', 200 === $res->get_status() );

$res = en_rest( 'GET', "/easynote/v1/public-notes/{$slug}" );
en_assert( 'shared note is 200 on public endpoint', 200 === $res->get_status() );
en_assert( 'public payload omits author', ! array_key_exists( 'author', (array) $res->get_data() ) );

// --- Ownership scoping: a second user must not see these notes ------------
$editor_id = wp_insert_user(
	array(
		'user_login' => 'en_smoke_editor_' . wp_generate_password( 5, false ),
		'user_pass'  => wp_generate_password( 12, false ),
		'role'       => 'editor',
	)
);
if ( ! is_wp_error( $editor_id ) ) {
	wp_set_current_user( $editor_id );
	$other_tree = EasyNote_Notes::build_tree( $editor_id, false );
	$count      = count( $other_tree['inbox'] );
	foreach ( $other_tree['folders'] as $f ) {
		$count += count( $f['notes'] );
	}
	en_assert( 'a non-owner sees none of the owner notes', 0 === $count );
	wp_delete_user( $editor_id );
}
wp_set_current_user( $admin_id );

// --- Cleanup --------------------------------------------------------------
wp_delete_post( $note_id, true );
wp_delete_post( $inbox_id, true );
wp_delete_term( $folder_id, $taxonomy );
wp_set_current_user( 0 );

echo "==========================\n";
echo "PASS: {$GLOBALS['en_pass']}  FAIL: {$GLOBALS['en_fail']}\n";

if ( $GLOBALS['en_fail'] > 0 ) {
	exit( 1 );
}
