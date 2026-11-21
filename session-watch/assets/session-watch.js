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
			message = ( strings.confirmUser || '' ).replace( '%s', button.getAttribute( 'data-sw-name' ) );
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
}() );
