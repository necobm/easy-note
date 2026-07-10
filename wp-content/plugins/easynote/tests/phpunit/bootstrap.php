<?php
/**
 * PHPUnit bootstrap for the EasyNote plugin.
 *
 * Requires the WordPress PHPUnit test library. Install it with:
 *   bin/install-wp-tests.sh wordpress_test root '' db latest
 * and point WP_TESTS_DIR at it (defaults to /tmp/wordpress-tests-lib).
 *
 * @package EasyNote
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

$_functions = $_tests_dir . '/includes/functions.php';
if ( ! file_exists( $_functions ) ) {
	echo "Could not find the WordPress test suite at {$_tests_dir}.\n";
	echo "Run bin/install-wp-tests.sh first (see tests/README.md).\n";
	exit( 1 );
}

require_once $_functions;

// Load the plugin under test as a must-use plugin before WP boots.
tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/easynote.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';
