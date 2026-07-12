/**
 * Thin fetch wrapper for the WordPress REST API.
 *
 * Adds same-origin cookies and the X-WP-Nonce header (when present) so
 * authenticated requests are accepted by WordPress's cookie auth. The public
 * read-only entry constructs this without a nonce.
 */

/**
 * A REST error carrying the HTTP status and WordPress error payload.
 */
export class WpFetchError extends Error {
	constructor( message, status, data ) {
		super( message );
		this.name = 'WpFetchError';
		this.status = status;
		this.data = data;
	}
}

/**
 * Build a fetch function bound to a REST root and (optional) nonce.
 *
 * @param {Object} opts
 * @param {string} opts.restUrl Root REST URL, e.g. https://site/wp-json/
 * @param {string} [opts.nonce] wp_rest nonce for authenticated requests.
 * @return {(path: string, options?: Object) => Promise<any>} fetch function.
 */
export function createWpFetch( { restUrl, nonce } ) {
	const root = restUrl.replace( /\/$/, '' );

	return async function wpFetch( path, { method = 'GET', body, headers = {} } = {} ) {
		const finalHeaders = { Accept: 'application/json', ...headers };

		if ( nonce ) {
			finalHeaders[ 'X-WP-Nonce' ] = nonce;
		}
		if ( body !== undefined ) {
			finalHeaders[ 'Content-Type' ] = 'application/json';
		}

		const response = await fetch( `${ root }/${ path.replace( /^\//, '' ) }`, {
			method,
			credentials: 'same-origin',
			headers: finalHeaders,
			body: body !== undefined ? JSON.stringify( body ) : undefined,
		} );

		if ( response.status === 204 ) {
			return null;
		}

		const data = await response.json().catch( () => null );

		if ( ! response.ok ) {
			const message = data && data.message ? data.message : `Request failed (${ response.status })`;
			throw new WpFetchError( message, response.status, data );
		}

		return data;
	};
}
