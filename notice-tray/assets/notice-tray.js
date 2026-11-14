/**
 * Notice Tray: move admin notices from the top of the screen into a panel
 * opened from the admin bar. Runs after WordPress' own common.js has made
 * notices dismissible, so dismiss buttons keep working inside the tray.
 */
( function ( $ ) {
	'use strict';

	var config = window.noticeTray || {};
	var i18n = config.i18n || {};

	var NOTICE_SELECTOR = 'div.notice, div.updated, div.error, div.update-nag';
	var SKIP_INSIDE = 'form, table, .postbox, #screen-meta, .notice-tray-panel, .media-modal, .customize-control';
	var SEVERITIES = [ 'warning', 'info', 'success' ];

	function format( text, number ) {
		return String( text || '' ).replace( '%d', number );
	}

	function severity( el ) {
		if ( el.classList.contains( 'notice-warning' ) || el.classList.contains( 'update-nag' ) ) {
			return 'warning';
		}
		if ( el.classList.contains( 'notice-success' ) || el.classList.contains( 'updated' ) ) {
			return 'success';
		}
		return 'info';
	}

	function matchesKeep( el ) {
		if ( ! config.keep ) {
			return false;
		}
		try {
			return el.matches( config.keep );
		} catch ( e ) {
			// An invalid selector from the filter should not break the admin.
			return false;
		}
	}

	function collectNotices() {
		var root = document.getElementById( 'wpbody-content' );
		if ( ! root ) {
			return [];
		}

		return Array.prototype.filter.call( root.querySelectorAll( NOTICE_SELECTOR ), function ( el ) {
			if ( matchesKeep( el ) ) {
				return false;
			}
			// Skip notices nested inside another notice, forms, tables and boxes.
			if ( el.parentElement && el.parentElement.closest( NOTICE_SELECTOR + ',' + SKIP_INSIDE ) ) {
				return false;
			}
			return window.getComputedStyle( el ).display !== 'none';
		} );
	}

	function build() {
		var node = document.getElementById( 'wp-admin-bar-notice-tray' );
		var button = node && node.querySelector( '.ab-item' );
		if ( ! button ) {
			return;
		}

		var notices = collectNotices();

		var panel = document.createElement( 'div' );
		panel.className = 'notice-tray-panel';
		panel.id = 'notice-tray-panel';
		panel.hidden = true;
		panel.setAttribute( 'role', 'dialog' );
		panel.setAttribute( 'aria-labelledby', 'notice-tray-title' );

		var header = document.createElement( 'div' );
		header.className = 'notice-tray-header';

		var title = document.createElement( 'h2' );
		title.id = 'notice-tray-title';
		title.className = 'notice-tray-title';
		title.textContent = i18n.title || 'Notices';

		var summary = document.createElement( 'span' );
		summary.className = 'notice-tray-summary';

		var close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'notice-tray-close';
		close.setAttribute( 'aria-label', i18n.close || 'Close' );
		close.innerHTML = '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';

		header.appendChild( title );
		header.appendChild( summary );
		header.appendChild( close );

		var list = document.createElement( 'div' );
		list.className = 'notice-tray-list';
		list.tabIndex = -1;

		var empty = document.createElement( 'p' );
		empty.className = 'notice-tray-empty';
		empty.innerHTML = '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>';
		empty.appendChild( document.createTextNode( i18n.empty || '' ) );

		var footer = document.createElement( 'div' );
		footer.className = 'notice-tray-footer';

		var hint = document.createElement( 'p' );
		hint.className = 'notice-tray-hint';
		hint.textContent = i18n.hint || '';

		var pause = document.createElement( 'a' );
		pause.className = 'notice-tray-pause';
		pause.href = config.pauseUrl || '#';
		pause.textContent = i18n.pause || '';

		footer.appendChild( hint );
		footer.appendChild( pause );

		panel.appendChild( header );
		panel.appendChild( list );
		panel.appendChild( empty );
		panel.appendChild( footer );
		document.body.appendChild( panel );

		// Most urgent first, keeping each plugin's own order within a group.
		SEVERITIES.forEach( function ( level ) {
			notices.forEach( function ( el ) {
				if ( severity( el ) === level ) {
					el.classList.add( 'notice-tray-item' );
					list.appendChild( el );
				}
			} );
		} );

		var live = document.createElement( 'span' );
		live.className = 'screen-reader-text';
		live.setAttribute( 'aria-live', 'polite' );
		panel.appendChild( live );

		button.setAttribute( 'role', 'button' );
		button.setAttribute( 'aria-controls', panel.id );
		button.setAttribute( 'aria-expanded', 'false' );
		button.setAttribute( 'aria-haspopup', 'dialog' );

		var lastCount = -1;

		function visibleNotices() {
			return Array.prototype.filter.call( list.children, function ( el ) {
				return el.classList.contains( 'notice-tray-item' ) && window.getComputedStyle( el ).display !== 'none';
			} );
		}

		function update() {
			var items = visibleNotices();
			var count = items.length;
			var hasWarning = items.some( function ( el ) {
				return severity( el ) === 'warning';
			} );

			node.classList.toggle( 'is-empty', count === 0 );
			node.classList.toggle( 'has-warning', hasWarning );
			node.querySelector( '.notice-tray-count' ).textContent = count > 99 ? '99+' : String( count );

			var label = count === 0 ? i18n.buttonNone : format( count === 1 ? i18n.buttonOne : i18n.buttonMany, count );
			button.setAttribute( 'aria-label', label || '' );
			summary.textContent = count ? format( count === 1 ? i18n.countOne : i18n.countMany, count ) : '';
			// Only touch attributes that change, so the observer below does not loop.
			if ( empty.hidden !== ( count !== 0 ) ) {
				empty.hidden = count !== 0;
			}
			if ( list.hidden !== ( count === 0 ) ) {
				list.hidden = count === 0;
			}

			// A dismissed notice takes its focused button with it; keep focus in the tray.
			if ( isOpen() && ! panel.contains( document.activeElement ) ) {
				( count ? list : close ).focus();
			}

			if ( lastCount !== -1 && count !== lastCount ) {
				live.textContent = summary.textContent || i18n.empty || '';
			}
			lastCount = count;
		}

		function isOpen() {
			return ! panel.hidden;
		}

		function open() {
			panel.hidden = false;
			node.classList.add( 'is-open' );
			button.setAttribute( 'aria-expanded', 'true' );
			close.focus();
		}

		function hide( returnFocus ) {
			if ( ! isOpen() ) {
				return;
			}
			panel.hidden = true;
			node.classList.remove( 'is-open' );
			button.setAttribute( 'aria-expanded', 'false' );
			if ( returnFocus ) {
				button.focus();
			}
		}

		button.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			if ( isOpen() ) {
				hide( true );
			} else {
				open();
			}
		} );

		button.addEventListener( 'keydown', function ( event ) {
			if ( event.key === ' ' ) {
				event.preventDefault();
				button.click();
			}
		} );

		close.addEventListener( 'click', function () {
			hide( true );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' && isOpen() ) {
				hide( true );
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( isOpen() && ! panel.contains( event.target ) && ! node.contains( event.target ) ) {
				hide( false );
			}
		} );

		// Dismissing a notice removes or hides it; keep the count in step.
		if ( window.MutationObserver ) {
			new window.MutationObserver( update ).observe( list, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: [ 'style', 'class', 'hidden' ],
			} );
		}

		$( document ).on( 'notice-tray:refresh', update );

		update();
		document.body.classList.add( 'notice-tray-ready' );
	}

	$( build );
} )( window.jQuery );
