<?php
/**
 * Front-end app shell: routing, login gating and asset delivery.
 *
 * @package EasyNote
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves the React SPA on the WordPress front end.
 *
 * Three custom routes are added: `/notes` and `/notes/{id}` (the logged-in app,
 * gated by auth_redirect()) and `/n/{slug}` (a public, read-only note view). Each
 * renders a minimal standalone HTML shell — deliberately not the active theme's
 * header/footer — containing the React root and the enqueued bundle. wp-admin is
 * never touched: the whole mechanism lives on the front-end template_redirect.
 */
final class EasyNote_App_Shell {

	/** Script/style handle for the logged-in app bundle. */
	const HANDLE = 'easynote-app';

	/** Script/style handle for the public read-only bundle. */
	const PUBLIC_HANDLE = 'easynote-public';

	/** Path (relative to the plugin dir) of the built assets. */
	const BUILD_DIR = 'app/build/';

	/**
	 * Wire up hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_app' ) );
		add_action( 'admin_notices', array( $this, 'maybe_warn_permalinks' ) );
	}

	/**
	 * Warn admins if plain permalinks are active — the app routes need pretty
	 * permalinks (rewrite rules) to resolve. Shown only to users who can fix it.
	 */
	public function maybe_warn_permalinks() {
		if ( ! current_user_can( 'manage_options' ) || '' !== get_option( 'permalink_structure' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			wp_kses(
				sprintf(
					/* translators: %s: URL of the Permalinks settings screen. */
					__( 'EasyNote needs pretty permalinks to serve the notes app. Please choose any option other than “Plain” on the <a href="%s">Permalinks settings</a> screen.', 'easynote' ),
					esc_url( admin_url( 'options-permalink.php' ) )
				),
				array( 'a' => array( 'href' => array() ) )
			)
		);
	}

	/**
	 * Register the front-end routes.
	 *
	 * Also invoked directly on activation so a rewrite flush captures the rules.
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule( '^notes/?$', 'index.php?easynote_route=1&easynote_view=home', 'top' );
		add_rewrite_rule( '^notes/([0-9]+)/?$', 'index.php?easynote_route=1&easynote_view=note&easynote_id=$matches[1]', 'top' );
		add_rewrite_rule( '^n/([a-zA-Z0-9-]+)/?$', 'index.php?easynote_route=1&easynote_view=public&easynote_slug=$matches[1]', 'top' );
	}

	/**
	 * Whitelist the plugin's query vars.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[]
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'easynote_route';
		$vars[] = 'easynote_view';
		$vars[] = 'easynote_id';
		$vars[] = 'easynote_slug';

		return $vars;
	}

	/**
	 * Render the SPA shell when the request matches one of our routes.
	 */
	public function maybe_render_app() {
		if ( ! get_query_var( 'easynote_route' ) ) {
			return;
		}

		$view = (string) get_query_var( 'easynote_view' );

		if ( 'public' === $view ) {
			$this->render_public();
		} else {
			// Gate on the site-wide logged-in cookie. auth_redirect() can't be
			// used here: its auth cookie is scoped to /wp-admin, so it is never
			// sent on a front-end path like /notes and would always redirect.
			if ( ! is_user_logged_in() ) {
				$request  = isset( $_SERVER['REQUEST_URI'] )
					? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
					: '/notes';
				$redirect = esc_url_raw( home_url( $request ) );
				wp_safe_redirect( wp_login_url( $redirect ) );
				exit;
			}
			$this->render_app( $view );
		}

		exit;
	}

	/* --------------------------------------------------------------------- */
	/* Rendering                                                              */
	/* --------------------------------------------------------------------- */

	/**
	 * Render the logged-in app shell.
	 *
	 * @param string $view 'home' or 'note'.
	 */
	private function render_app( $view ) {
		$note_id = (int) get_query_var( 'easynote_id' );
		$user    = wp_get_current_user();

		$config = array(
			'restUrl'       => esc_url_raw( rest_url() ),
			'restNamespace' => EasyNote_REST_API::NAMESPACE,
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'appBase'       => esc_url_raw( home_url( '/notes' ) ),
			'homeUrl'       => esc_url_raw( home_url( '/' ) ),
			'view'          => $view,
			'noteId'        => $note_id > 0 ? $note_id : null,
			'isPublic'      => false,
			'currentUser'   => array(
				'id'               => (int) $user->ID,
				'name'             => $user->display_name,
				'canManageOthers'  => current_user_can( 'edit_others_easynote_notes' ),
				'canUpload'        => current_user_can( 'upload_files' ),
			),
		);

		$this->enqueue( self::HANDLE, 'index', $config );
		$this->output_shell( self::HANDLE, 'easynote-app', __( 'Notes', 'easynote' ) );
	}

	/**
	 * Render the public read-only note shell.
	 */
	private function render_public() {
		$slug = sanitize_title( (string) get_query_var( 'easynote_slug' ) );

		$config = array(
			'restUrl'       => esc_url_raw( rest_url() ),
			'restNamespace' => EasyNote_REST_API::NAMESPACE,
			'homeUrl'       => esc_url_raw( home_url( '/' ) ),
			'isPublic'      => true,
			'publicSlug'    => $slug,
		);

		$this->enqueue( self::PUBLIC_HANDLE, 'public', $config );
		$this->output_shell( self::PUBLIC_HANDLE, 'easynote-public', __( 'Note', 'easynote' ) );
	}

	/**
	 * Enqueue a built bundle and attach the bootstrap config.
	 *
	 * Reads the webpack-generated `*.asset.php` manifest for dependencies and a
	 * content-hash version. Degrades gracefully to an admin notice string when the
	 * build has not been produced yet (dev bootstrap before the SPA exists).
	 *
	 * @param string $handle Script/style handle.
	 * @param string $entry  Build entry name (e.g. 'index', 'public').
	 * @param array  $config Bootstrap data exposed as window.EasyNoteConfig.
	 */
	private function enqueue( $handle, $entry, $config ) {
		$asset_file = EASYNOTE_DIR . self::BUILD_DIR . $entry . '.asset.php';

		$asset = file_exists( $asset_file )
			? include $asset_file
			: array( 'dependencies' => array(), 'version' => EASYNOTE_VERSION );

		wp_enqueue_script(
			$handle,
			EASYNOTE_URL . self::BUILD_DIR . $entry . '.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		$style_path = EASYNOTE_DIR . self::BUILD_DIR . $entry . '.css';
		if ( file_exists( $style_path ) ) {
			wp_enqueue_style(
				$handle,
				EASYNOTE_URL . self::BUILD_DIR . $entry . '.css',
				array(),
				$asset['version']
			);
		}

		wp_add_inline_script(
			$handle,
			'window.EasyNoteConfig = ' . wp_json_encode( $config ) . ';',
			'before'
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( $handle, 'easynote', EASYNOTE_DIR . 'languages' );
		}
	}

	/**
	 * Output the minimal HTML document that hosts the React root.
	 *
	 * @param string $handle   Enqueued handle (for wp_print_scripts targeting).
	 * @param string $root_id  DOM id of the React mount node.
	 * @param string $title    Document title.
	 */
	private function output_shell( $handle, $root_id, $title ) {
		nocache_headers();

		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,follow">
	<title><?php echo esc_html( $title . ' — ' . get_bloginfo( 'name' ) ); ?></title>
	<?php wp_print_styles( array( $handle ) ); ?>
</head>
<body class="easynote-app-body">
	<div id="<?php echo esc_attr( $root_id ); ?>"></div>
	<?php wp_print_scripts( array( $handle ) ); ?>
</body>
</html>
		<?php
	}
}
