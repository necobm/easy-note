<?php
/**
 * EasyNote WP-CLI smoke test (assertion-based).
 *
 * Runs the plugin's logic units against a live WordPress install and reports
 * PASS/FAIL. This is the fallback verification used when the full PHPUnit
 * harness (svn/composer/phpunit + test DB) is not available in the environment.
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

echo "EasyNote WP-CLI smoke test\n";
echo "==========================\n";

// --- Fixtures -------------------------------------------------------------
$root = wp_insert_term( 'EN Smoke Root ' . wp_generate_password( 6, false ), 'category' );
$root_id = is_wp_error( $root ) ? 0 : (int) $root['term_id'];
$child = wp_insert_term( 'EN Smoke Child ' . wp_generate_password( 6, false ), 'category', array( 'parent' => $root_id ) );
$child_id = is_wp_error( $child ) ? 0 : (int) $child['term_id'];

$note_in_child = wp_insert_post(
	array(
		'post_title'  => 'EN Smoke Note',
		'post_status' => 'publish',
		'post_type'   => 'post',
		'post_category' => array( $child_id ),
	)
);

$inbox_note = wp_insert_post(
	array(
		'post_title'  => 'EN Smoke Inbox Note',
		'post_status' => 'publish',
		'post_type'   => 'post',
	)
);

// --- Classes load ---------------------------------------------------------
en_assert( 'plugin classes are loaded', class_exists( 'EasyNote_Notes' ) && class_exists( 'EasyNote_Category_Page' ) && class_exists( 'EasyNote_Roles' ) );

// --- Activation idempotency ----------------------------------------------
EasyNote_Activator::activate();
EasyNote_Activator::activate(); // twice -> must not error or duplicate.
en_assert( 'editor role exists after (repeated) activation', null !== get_role( 'editor' ) );
en_assert( 'activation records version option', EASYNOTE_VERSION === get_option( 'easynote_version' ) );

// --- Tree helpers ---------------------------------------------------------
$root_ids = wp_list_pluck( EasyNote_Notes::get_child_categories( 0 ), 'term_id' );
en_assert( 'get_child_categories(0) includes our root', in_array( $root_id, $root_ids, true ) );
en_assert( 'get_child_categories(0) excludes default (Inbox) category', ! in_array( EasyNote_Notes::default_category_id(), $root_ids, true ) );

$child_ids = wp_list_pluck( EasyNote_Notes::get_child_categories( $root_id ), 'term_id' );
en_assert( 'get_child_categories(root) returns the child', array( $child_id ) === $child_ids );

$notes = wp_list_pluck( EasyNote_Notes::get_notes_in_category( $child_id ), 'ID' );
en_assert( 'get_notes_in_category(child) includes the filed note', in_array( $note_in_child, $notes, true ) );

$inbox_ids = wp_list_pluck( EasyNote_Notes::get_inbox_notes(), 'ID' );
en_assert( 'get_inbox_notes() includes the uncategorized note', in_array( $inbox_note, $inbox_ids, true ) );
en_assert( 'get_inbox_notes() excludes the categorized note', ! in_array( $note_in_child, $inbox_ids, true ) );

// --- Alphabetical ordering ------------------------------------------------
$a = wp_insert_term( 'ZZZ EN Order', 'category', array( 'parent' => $root_id ) );
$b = wp_insert_term( 'AAA EN Order', 'category', array( 'parent' => $root_id ) );
$order = wp_list_pluck( EasyNote_Notes::get_child_categories( $root_id ), 'name' );
$aaa = array_search( 'AAA EN Order', $order, true );
$zzz = array_search( 'ZZZ EN Order', $order, true );
en_assert( 'child categories are alphabetical', false !== $aaa && false !== $zzz && $aaa < $zzz );

// --- URL helpers ----------------------------------------------------------
en_assert( 'url(0) is the plain EasyNote screen', admin_url( 'admin.php?page=easynote' ) === EasyNote_Category_Page::url( 0 ) );
en_assert( 'url(N) carries the node id', false !== strpos( EasyNote_Category_Page::url( $root_id ), 'node=' . $root_id ) );
en_assert( 'new_note_url(N) carries easynote_cat', false !== strpos( EasyNote_Category_Page::new_note_url( $child_id ), 'easynote_cat=' . $child_id ) );

// --- Role logic -----------------------------------------------------------
$admin_id  = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
$editor_id = (int) ( get_users( array( 'role' => 'editor', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );

if ( $editor_id ) {
	wp_set_current_user( $editor_id );
	en_assert( 'editor is a restricted EasyNote user', EasyNote_Roles::is_restricted_user() );
	en_assert( 'editor is not a site admin', ! EasyNote_Roles::is_site_admin() );
}
if ( $admin_id ) {
	wp_set_current_user( $admin_id );
	en_assert( 'admin is a site admin', EasyNote_Roles::is_site_admin() );
	en_assert( 'admin is not restricted', ! EasyNote_Roles::is_restricted_user() );
}
wp_set_current_user( 0 );

// --- Cleanup --------------------------------------------------------------
wp_delete_post( $note_in_child, true );
wp_delete_post( $inbox_note, true );
wp_delete_term( $child_id, 'category' );
if ( ! is_wp_error( $a ) ) { wp_delete_term( (int) $a['term_id'], 'category' ); }
if ( ! is_wp_error( $b ) ) { wp_delete_term( (int) $b['term_id'], 'category' ); }
wp_delete_term( $root_id, 'category' );

echo "==========================\n";
echo "PASS: {$GLOBALS['en_pass']}  FAIL: {$GLOBALS['en_fail']}\n";

if ( $GLOBALS['en_fail'] > 0 ) {
	exit( 1 );
}
