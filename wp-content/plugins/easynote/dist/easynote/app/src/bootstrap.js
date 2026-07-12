/**
 * Reads and validates the PHP-injected bootstrap object.
 *
 * PHP renders `window.EasyNoteConfig` inline before the bundle (see
 * EasyNote_App_Shell::enqueue). If it is missing the app cannot talk to the REST
 * API, so we fail loudly rather than rendering a blank page.
 */
export function getConfig() {
	const config = typeof window !== 'undefined' ? window.EasyNoteConfig : undefined;

	if ( ! config || ! config.restUrl ) {
		throw new Error(
			'EasyNote: window.EasyNoteConfig is missing. The app bundle was enqueued without its bootstrap data.'
		);
	}

	return config;
}
