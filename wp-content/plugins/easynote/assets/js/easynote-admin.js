/**
 * EasyNote admin: relocate the note tree into the sidebar and wire
 * expand/collapse + active-node highlighting. Vanilla JS, no dependencies.
 */
( function () {
	'use strict';

	function init() {
		var source = document.getElementById( 'easynote-tree-source' );
		var menuItem = document.getElementById( 'toplevel_page_easynote' );
		if ( ! source || ! menuItem ) {
			return;
		}

		var tree = source.querySelector( '.easynote-tree' );
		if ( ! tree ) {
			return;
		}

		// Move the tree under the EasyNote menu item and drop the empty shell.
		var container = document.createElement( 'div' );
		container.className = 'easynote-tree-wrap';
		container.appendChild( tree );
		menuItem.appendChild( container );
		source.parentNode.removeChild( source );

		wireToggles( tree );
		highlightActive( tree );
	}

	function wireToggles( tree ) {
		var toggles = tree.querySelectorAll( '.easynote-toggle' );
		Array.prototype.forEach.call( toggles, function ( toggle ) {
			if ( toggle.tagName !== 'BUTTON' ) {
				return;
			}
			toggle.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				var node = toggle.closest( '.easynote-node' );
				if ( node ) {
					var open = node.classList.toggle( 'is-open' );
					toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				}
			} );
		} );
	}

	function highlightActive( tree ) {
		var params = new URLSearchParams( window.location.search );
		var link = null;

		if ( /\/post\.php$/.test( window.location.pathname ) && params.get( 'post' ) ) {
			link = tree.querySelector( '.easynote-link[data-post="' + cssEscape( params.get( 'post' ) ) + '"]' );
		} else if ( params.get( 'page' ) === 'easynote' && params.get( 'node' ) ) {
			link = tree.querySelector( '.easynote-link[data-node="' + cssEscape( params.get( 'node' ) ) + '"]' );
		}

		if ( ! link ) {
			return;
		}

		link.classList.add( 'is-active' );

		// Expand every ancestor branch so the active node is visible.
		var parent = link.closest( '.easynote-node' );
		while ( parent ) {
			parent.classList.add( 'is-open' );
			var toggle = parent.querySelector( ':scope > .easynote-row > .easynote-toggle' );
			if ( toggle && toggle.tagName === 'BUTTON' ) {
				toggle.setAttribute( 'aria-expanded', 'true' );
			}
			parent = parent.parentNode ? parent.parentNode.closest( '.easynote-node' ) : null;
		}
	}

	function cssEscape( value ) {
		if ( window.CSS && typeof window.CSS.escape === 'function' ) {
			return window.CSS.escape( value );
		}
		return String( value ).replace( /["\\]/g, '\\$&' );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
