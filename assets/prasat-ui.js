/*
 * Prasat UI kit — shared behaviour for Prasat Digital plugin screens. No dependencies.
 * Keep this file identical in every Prasat Digital plugin.
 *
 *  - data-pd-confirm="Question?" on a form or link asks before going ahead.
 *  - Forms that really submit get a busy button and can't be sent twice.
 */
( function () {
	'use strict';

	var SCOPE = '.pd-app, .lifeboat';

	// Ask first (runs before any other submit handler).
	document.addEventListener(
		'submit',
		function ( e ) {
			var f = e.target;
			var msg = f.getAttribute && f.getAttribute( 'data-pd-confirm' );
			if ( f.getAttribute && '1' === f.getAttribute( 'data-pd-sending' ) ) {
				e.preventDefault(); // Already on its way.
				return;
			}
			if ( msg && ! window.confirm( msg ) ) {
				e.preventDefault();
				e.stopImmediatePropagation();
			}
		},
		true
	);

	document.addEventListener(
		'click',
		function ( e ) {
			var a = e.target.closest && e.target.closest( 'a[data-pd-confirm]' );
			if ( a && ! window.confirm( a.getAttribute( 'data-pd-confirm' ) ) ) {
				e.preventDefault();
				e.stopImmediatePropagation();
			}
		},
		true
	);

	// Busy state once a submission is really going ahead (nobody cancelled it).
	document.addEventListener( 'submit', function ( e ) {
		var f = e.target;
		if ( ! f.closest || ! f.closest( SCOPE ) || f.hasAttribute( 'data-pd-no-busy' ) || 'dialog' === ( f.getAttribute( 'method' ) || '' ).toLowerCase() ) {
			return;
		}
		window.setTimeout( function () {
			if ( e.defaultPrevented ) {
				return;
			}
			f.setAttribute( 'data-pd-sending', '1' );
			var b = e.submitter || f.querySelector( 'button[type="submit"], button:not([type]), input[type="submit"]' );
			if ( b ) {
				// Not "disabled": that would drop the button's own name/value from the request.
				b.setAttribute( 'aria-busy', 'true' );
				b.setAttribute( 'aria-disabled', 'true' );
			}
		}, 0 );
	} );

	// Action links (clear cache, roll back…) look busy while the next page loads.
	document.addEventListener( 'click', function ( e ) {
		var a = e.target.closest && e.target.closest( SCOPE.split( ', ' ).map( function ( s ) {
			return s + ' a.button[href*="admin-post.php"]';
		} ).join( ', ' ) );
		if ( a && ! e.defaultPrevented ) {
			window.setTimeout( function () {
				if ( ! e.defaultPrevented ) {
					a.setAttribute( 'aria-busy', 'true' );
				}
			}, 0 );
		}
	} );

	// Coming back with the browser's Back button: nothing is busy any more.
	window.addEventListener( 'pageshow', function () {
		document.querySelectorAll( '[data-pd-sending]' ).forEach( function ( f ) {
			f.removeAttribute( 'data-pd-sending' );
		} );
		document.querySelectorAll( '[aria-busy="true"]' ).forEach( function ( b ) {
			b.removeAttribute( 'aria-busy' );
			b.removeAttribute( 'aria-disabled' );
		} );
	} );
} )();
