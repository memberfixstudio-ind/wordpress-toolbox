/**
 * Session Watch: ask before signing a person out everywhere or signing out
 * everyone else. Ending a single device needs no confirmation, because it is
 * easy to undo: that person just logs in again.
 */
( function () {
	'use strict';

	var strings = window.sessionWatch || {};

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		if ( ! form || ! form.classList || ! form.classList.contains( 'sw-action' ) ) {
			return;
		}

		var button = form.querySelector( 'button' );
		if ( ! button ) {
			return;
		}

		var message = '';
		if ( button.hasAttribute( 'data-sw-others' ) ) {
			message = strings.confirmOthers || '';
		} else if ( button.getAttribute( 'data-sw-name' ) ) {
			var name = button.getAttribute( 'data-sw-name' );
			// A function keeps "$&" and similar in a display name from being read as a pattern.
			message = ( strings.confirmUser || '' ).replace( '%s', function () {
				return name;
			} );
		}

		if ( message && ! window.confirm( message ) ) {
			event.preventDefault();
			return;
		}

		// Stop a double click from sending the request twice.
		if ( form.getAttribute( 'data-sw-sent' ) ) {
			event.preventDefault();
			return;
		}
		form.setAttribute( 'data-sw-sent', '1' );
		button.setAttribute( 'aria-disabled', 'true' );
	} );

	// Coming back with the Back button can restore the page from the browser's
	// cache with every button still locked, so unlock them again.
	window.addEventListener( 'pageshow', function ( event ) {
		if ( ! event.persisted ) {
			return;
		}
		Array.prototype.forEach.call( document.querySelectorAll( 'form.sw-action[data-sw-sent]' ), function ( form ) {
			form.removeAttribute( 'data-sw-sent' );
			var button = form.querySelector( 'button' );
			if ( button ) {
				button.removeAttribute( 'aria-disabled' );
			}
		} );
	} );
}() );
