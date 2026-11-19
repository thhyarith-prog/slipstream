/*
 * Prasat UI kit — shared behaviour for Prasat Digital plugin screens. No dependencies.
 * Keep this file identical in every Prasat Digital plugin.
 *
 *  - data-pd-confirm="Question?" on a form or link asks before going ahead.
 *  - Forms that really submit get a busy button and can't be sent twice.
 *  - Prasat loaders, three animations of the stepped-temple mark:
 *      fill  – the temple fills with the real progress (scans, backups, optimizing, clean-ups);
 *      build – the tiers build up, for waits of unknown length;
 *      glow  – a light runs down the tiers, inside busy buttons.
 *    Every progress bar of the family (.pd-progress, .bastion-progress, .lb-progress) gets a
 *    fill temple automatically; busy buttons glow automatically. Scripts can also call
 *    PrasatUI.loader( 'build' | 'fill' | 'glow' ) and append loader.el where they need it.
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
	/* ================================================================ loaders */

	// The Prasat mark: four tiers, top to base, as [x, y, width]. viewBox 8 7 48 40.
	var TIERS = [ [ 25, 9, 14 ], [ 20, 18, 24 ], [ 15, 27, 34 ], [ 10, 36, 44 ] ];

	function temple( cls ) {
		var s = '<svg class="' + cls + '" viewBox="8 7 48 40" aria-hidden="true" focusable="false">';
		TIERS.forEach( function ( t, i ) {
			s += '<rect class="pd-t pd-t' + ( i + 1 ) + '" x="' + t[0] + '" y="' + t[1] + '" width="' + t[2] + '" height="6" rx="0.6"/>';
		} );
		return s + '</svg>';
	}

	// Styles live here (not in prasat-ui.css) so the loaders also work on screens that only load this script.
	var GLOW = "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='8 7 48 40'%3E%3Cstyle%3Erect%7Banimation:g 1.6s ease-in-out infinite;opacity:.3%7D.b%7Banimation-delay:.2s%7D.c%7Banimation-delay:.4s%7D.d%7Banimation-delay:.6s%7D@keyframes g%7B0%25,100%25%7Bopacity:.3%7D35%25%7Bopacity:1%7D%7D%3C/style%3E%3Crect x='25' y='9' width='14' height='6'/%3E%3Crect class='b' x='20' y='18' width='24' height='6'/%3E%3Crect class='c' x='15' y='27' width='34' height='6'/%3E%3Crect class='d' x='10' y='36' width='44' height='6'/%3E%3C/svg%3E\")";
	var STILL = "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='8 7 48 40'%3E%3Crect x='25' y='9' width='14' height='6'/%3E%3Crect x='20' y='18' width='24' height='6'/%3E%3Crect x='15' y='27' width='34' height='6'/%3E%3Crect x='10' y='36' width='44' height='6'/%3E%3C/svg%3E\")";
	var CSS = [
		'.pd-loader{display:flex;align-items:center;gap:12px;margin:10px 0;color:#44403c}',
		'.pd-loader[hidden]{display:none}',
		'.pd-loader__art{position:relative;flex:none;width:56px;height:47px}',
		'.pd-loader__art svg{position:absolute;inset:0;width:100%;height:100%;overflow:visible}',
		'.pd-loader .pd-t1{fill:#2dd4bf}.pd-loader .pd-t2{fill:#38bdf8}.pd-loader .pd-t3{fill:#6366f1}.pd-loader .pd-t4{fill:#0c1d3f}',
		'.pd-loader__ghost .pd-t{fill:#e7e5e4!important}',
		'.pd-loader__level{position:absolute;inset:0;clip-path:inset(var(--pd-cut,100%) 0 0 0);transition:clip-path .45s ease}',
		'.pd-loader__info{display:flex;flex-direction:column;gap:3px;min-width:0}',
		'.pd-loader__text:empty{display:none}',
		'.pd-loader__pct{font-size:20px;font-weight:700;color:#1c1917;font-variant-numeric:tabular-nums;line-height:1}',
		'.pd-loader__text{font-size:13px;color:#6b6560}',
		'.pd-loader--sm .pd-loader__art{width:24px;height:20px}',
		'.pd-loader--lg .pd-loader__art{width:96px;height:80px}',
		'.pd-loader--stack{flex-direction:column;gap:8px;text-align:center}',
		'.pd-loader--build .pd-t{transform-box:fill-box;transform-origin:50% 100%;animation:pd-build 2.4s cubic-bezier(.34,1.4,.64,1) infinite}',
		'.pd-loader--build .pd-t4{animation-delay:0s}.pd-loader--build .pd-t3{animation-delay:.18s}.pd-loader--build .pd-t2{animation-delay:.36s}.pd-loader--build .pd-t1{animation-delay:.54s}',
		'@keyframes pd-build{0%{opacity:0;transform:translateY(-6px) scaleY(.6)}18%,78%{opacity:1;transform:none}100%{opacity:0;transform:none}}',
		'.pd-loader--glow .pd-t{animation:pd-glow 1.6s ease-in-out infinite;opacity:.3}',
		'.pd-loader--glow .pd-t2{animation-delay:.2s}.pd-loader--glow .pd-t3{animation-delay:.4s}.pd-loader--glow .pd-t4{animation-delay:.6s}',
		'@keyframes pd-glow{0%,100%{opacity:.3}35%{opacity:1}}',
		':is(.pd-app,.lifeboat) [aria-busy="true"]{pointer-events:none}',
		':is(.pd-app,.lifeboat) :is(.button,button)[aria-busy="true"]::before{content:"";display:inline-block;flex:none;width:18px;height:15px;margin-right:6px;vertical-align:-2px;border:0;border-radius:0;animation:none;background:currentColor;-webkit-mask:' + GLOW + ' center/contain no-repeat;mask:' + GLOW + ' center/contain no-repeat}',
		'@media (prefers-reduced-motion:reduce){.pd-loader .pd-t{animation:none!important;opacity:1}.pd-loader__level{transition:none}:is(.pd-app,.lifeboat) :is(.button,button)[aria-busy="true"]::before{-webkit-mask-image:' + STILL + ';mask-image:' + STILL + '}}'
	].join( '' );

	function injectStyles() {
		if ( document.getElementById( 'pd-loader-css' ) ) {
			return;
		}
		var st = document.createElement( 'style' );
		st.id = 'pd-loader-css';
		st.textContent = CSS;
		document.head.appendChild( st );
	}

	/**
	 * @param {string} mode  'fill' | 'build' | 'glow'
	 * @param {object} opts  { size: 'sm'|'lg', stack: bool, text: string, label: string }
	 * @return {{el: HTMLElement, set: function(number, string=), text: function(string)}}
	 */
	function loader( mode, opts ) {
		injectStyles();
		opts = opts || {};
		mode = 'fill' === mode || 'glow' === mode ? mode : 'build';
		var el = document.createElement( 'div' );
		el.className = 'pd-loader pd-loader--' + mode + ( opts.size ? ' pd-loader--' + opts.size : '' ) + ( opts.stack ? ' pd-loader--stack' : '' );
		var art = 'fill' === mode
			? temple( 'pd-loader__ghost' ) + '<span class="pd-loader__level">' + temple( '' ) + '</span>'
			: temple( '' );
		el.innerHTML = '<span class="pd-loader__art">' + art + '</span><span class="pd-loader__info">' + ( 'fill' === mode ? '<span class="pd-loader__pct">0%</span>' : '' ) + '<span class="pd-loader__text"></span></span>';
		var pct = el.querySelector( '.pd-loader__pct' );
		var txt = el.querySelector( '.pd-loader__text' );
		var lvl = el.querySelector( '.pd-loader__level' );
		if ( 'fill' === mode ) {
			el.setAttribute( 'role', 'progressbar' );
			el.setAttribute( 'aria-valuemin', '0' );
			el.setAttribute( 'aria-valuemax', '100' );
			el.setAttribute( 'aria-label', opts.label || 'Progress' );
		} else {
			el.setAttribute( 'role', 'status' );
		}
		var api = {
			el: el,
			set: function ( p, message ) {
				p = Math.max( 0, Math.min( 100, Number( p ) || 0 ) );
				if ( lvl ) {
					// The tiers span y 9–42 of the 40-unit-high box that starts at y 7.
					lvl.style.setProperty( '--pd-cut', ( 5 + ( 1 - p / 100 ) * 82.5 ).toFixed( 2 ) + '%' );
					pct.textContent = Math.round( p ) + '%';
					el.setAttribute( 'aria-valuenow', String( Math.round( p ) ) );
				}
				if ( undefined !== message ) {
					api.text( message );
				}
				return api;
			},
			text: function ( message ) {
				txt.textContent = message || '';
				return api;
			}
		};
		if ( opts.text ) {
			api.text( opts.text );
		}
		return 'fill' === mode ? api.set( opts.value || 0 ) : api;
	}

	// Give every progress bar of the family a fill temple that follows it.
	function wireBars( root ) {
		( root || document ).querySelectorAll( '.pd-progress, .bastion-progress, .lb-progress' ).forEach( function ( box ) {
			if ( box.getAttribute( 'data-pd-loader' ) ) {
				return;
			}
			var span = box.querySelector( '.bastion-progress__bar span' ) || box.querySelector( ':scope > span' );
			if ( ! span ) {
				return;
			}
			box.setAttribute( 'data-pd-loader', '1' );
			var l = loader( 'fill' );
			if ( box.classList.contains( 'bastion-progress' ) ) {
				box.insertBefore( l.el, box.firstChild ); // Hidden and shown with its box.
			} else {
				box.parentNode.insertBefore( l.el, box );
				var sync = function () {
					l.el.hidden = box.hidden || 'none' === window.getComputedStyle( box ).display;
				};
				sync();
				new MutationObserver( sync ).observe( box, { attributes: true, attributeFilter: [ 'hidden', 'style', 'class' ] } );
			}
			var follow = function () {
				l.set( parseFloat( span.style.width ) || 0 );
			};
			follow();
			new MutationObserver( follow ).observe( span, { attributes: true, attributeFilter: [ 'style' ] } );
		} );
	}

	window.PrasatUI = window.PrasatUI || { loader: loader, wireBars: wireBars };
	injectStyles();
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			wireBars();
		} );
	} else {
		wireBars();
	}
} )();
