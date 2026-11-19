/* Slipstream admin. No dependencies. */
( function () {
	'use strict';

	var S = window.Slipstream || {};
	var t = S.i18n || {};

	function post( action ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', S.nonce );
		return fetch( S.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.text().then( function ( txt ) {
					if ( '-1' === txt.trim() || 403 === r.status ) {
						throw new Error( t.expired ); // Nonce expired: WordPress answers "-1".
					}
					try {
						return JSON.parse( txt );
					} catch ( e ) {
						throw new Error( t.error );
					}
				} );
			} )
			.then( function ( j ) {
				if ( ! j || ! j.success ) {
					throw new Error( ( j && j.data && j.data.message ) || t.error );
				}
				return j.data;
			} );
	}

	function set( root, key, text ) {
		var n = root.querySelector( '[data-k="' + key + '"]' );
		if ( n ) {
			n.textContent = text;
		}
		return n;
	}

	// A switch shows or hides the options that belong to it.
	document.addEventListener( 'change', function ( e ) {
		var input = e.target;
		if ( input.matches && input.matches( '.pd-switch input' ) ) {
			var row = input.closest( '.pd-row' );
			if ( row ) {
				row.classList.toggle( 'is-off', ! input.checked );
			}
		}
	} );

	// "Clean selected" is only available while something is ticked.
	var dbForm = document.getElementById( 'ss-db-form' );
	if ( dbForm ) {
		var dbBtn = dbForm.querySelector( '[data-db-clean]' );
		var syncDb = function () {
			dbBtn.disabled = ! dbForm.querySelector( 'input[name="items[]"]:checked' );
		};
		dbForm.addEventListener( 'change', syncDb );
		syncDb();
	}

	// Speed test.
	var card = document.getElementById( 'ss-speed' );
	if ( card ) {
		var speedBtn = card.querySelector( '[data-act="speed"]' );
		speedBtn.addEventListener( 'click', function () {
			var warn = card.querySelector( '[data-k="warn"]' );
			warn.hidden = true;
			speedBtn.disabled = true;
			speedBtn.setAttribute( 'aria-busy', 'true' );
			set( card, 'when', t.testing );
			post( 'slipstream_speed' )
				.then( function ( r ) {
					var nf = new Intl.NumberFormat();
					set( card, 'without', nf.format( r.without ) );
					set( card, 'with', nf.format( r.with ) );
					var f = set( card, 'factor', t.faster.replace( '%s', nf.format( r.factor ) ) );
					f.hidden = ! r.factor || r.factor < 1.2;
					card.querySelector( '[data-k="bar"]' ).style.width = Math.max( 2, Math.min( 100, Math.round( 100 * r.with / Math.max( 1, r.without ) ) ) ) + '%';
					set( card, 'when', t.justNow );
					if ( ! r.hit ) {
						warn.textContent = t.notCached;
						warn.hidden = false;
					}
				} )
				.catch( function ( err ) {
					set( card, 'when', '' );
					warn.textContent = err.message;
					warn.hidden = false;
				} )
				.then( function () {
					speedBtn.disabled = false;
					speedBtn.removeAttribute( 'aria-busy' );
				} );
		} );
	}

	// Live cache / preload numbers on the dashboard.
	var pages = document.querySelector( '[data-k="pages"]' );
	if ( pages ) {
		var wasRunning = false;
		var tick = function () {
			post( 'slipstream_status' )
				.then( function ( r ) {
					var root = document;
					set( root, 'pages', new Intl.NumberFormat().format( r.pages ) );
					set( root, 'bytes', r.bytes );
					set( root, 'pdone', r.preload.done );
					set( root, 'ptotal', r.preload.total );
					var bar = root.querySelector( '[data-k="pbar"]' );
					if ( bar ) {
						bar.style.width = ( r.preload.total ? Math.round( 100 * r.preload.done / r.preload.total ) : 0 ) + '%';
					}
					if ( r.preload.running ) {
						wasRunning = true;
						set( root, 'pstate', t.running );
					} else if ( wasRunning ) {
						set( root, 'pstate', t.finished ); // Only when a run we were watching ends.
					}
					if ( r.preload.running ) {
						window.setTimeout( tick, 4000 );
					}
				} )
				.catch( function () {} );
		};
		window.setTimeout( tick, 1500 );
	}
} )();
