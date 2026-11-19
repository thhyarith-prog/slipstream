<?php
/**
 * Front-end HTML optimizations, applied once per page before it is cached:
 * minify HTML, defer or delay JavaScript, lazy-load media, YouTube "click to load" previews,
 * preconnect / font preload hints and CDN rewriting.
 *
 * Script order is never changed: once one script is deferred, every later inline script is
 * deferred too (as a data: URI), so code that depends on a library still runs after it.
 *
 * @package Slipstream
 */

defined( 'ABSPATH' ) || exit;

class Slipstream_Optimize {

	/** jQuery stays render-blocking with "defer" because so many themes use it inline. */
	const DEFER_SAFE = array( '/jquery.min.js', '/jquery.js', '/jquery-migrate.min.js', '/jquery-migrate.js' );

	/** @var array Protected blocks (script, style, pre, textarea) while the HTML is edited. */
	private static $blocks = array();

	/** Should this request's HTML be optimized? */
	public static function wants() {
		$s = Slipstream_Settings::all();
		if ( is_admin() || is_feed() || is_embed() || is_customize_preview() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}
		if ( isset( $_GET['slipstream_off'] ) ) { // phpcs:ignore -- debugging switch, read-only.
			return false;
		}
		if ( is_user_logged_in() && ! $s['optimize_logged_in'] ) {
			return false;
		}
		if ( function_exists( 'amp_is_request' ) && amp_is_request() ) {
			return false;
		}
		return $s['minify_html'] || $s['defer_js'] || $s['delay_js'] || $s['lazy_media'] || $s['youtube_facade'] || $s['preconnect'] || $s['preload_fonts'] || $s['cdn_url'];
	}

	public static function process( $html ) {
		$s = Slipstream_Settings::all();
		self::$blocks = array();

		$html = self::scripts( $html, $s );
		// Protect code and preformatted text from every HTML edit below.
		$html = preg_replace_callback(
			'#<(script|style|pre|textarea)\b[^>]*>.*?</\1\s*>#is',
			function ( $m ) {
				$key                  = "\x1A" . count( self::$blocks ) . "\x1A";
				self::$blocks[ $key ] = $m[0];
				return $key;
			},
			$html
		);
		if ( null === $html ) {
			return func_get_arg( 0 ); // Regex limit hit on a huge page: leave it untouched.
		}
		if ( $s['youtube_facade'] ) {
			$html = self::youtube( $html );
		}
		if ( $s['lazy_media'] ) {
			$html = self::lazy( $html );
		}
		if ( $s['minify_html'] ) {
			$html = self::minify( $html );
		}
		$html = strtr( $html, self::$blocks );
		self::$blocks = array();
		$html = self::head_hints( $html, $s );
		if ( $s['cdn_url'] ) {
			$html = self::cdn( $html, $s['cdn_url'] );
		}
		return $html;
	}

	/* ============================================================ scripts */

	private static function attr( $tag, $name ) {
		return preg_match( '/\s' . preg_quote( $name, '/' ) . '\s*=\s*(["\'])(.*?)\1/is', $tag, $m ) ? html_entity_decode( $m[2], ENT_QUOTES ) : ( preg_match( '/\s' . preg_quote( $name, '/' ) . '\s*=\s*([^\s>]+)/i', $tag, $m ) ? $m[1] : null );
	}

	private static function has_attr( $tag, $name ) {
		return (bool) preg_match( '/\s' . preg_quote( $name, '/' ) . '(\s|=|>|\/)/i', $tag );
	}

	private static function matches( $haystack, array $needles ) {
		foreach ( $needles as $n ) {
			if ( '' !== trim( $n ) && false !== stripos( $haystack, trim( $n ) ) ) {
				return true;
			}
		}
		return false;
	}

	private static function scripts( $html, array $s ) {
		if ( ! $s['defer_js'] && ! $s['delay_js'] ) {
			return $html;
		}
		$deferring = false;
		$delayed   = 0;
		$out = preg_replace_callback(
			'#(<script\b[^>]*>)(.*?)(</script\s*>)#is',
			function ( $m ) use ( $s, &$deferring, &$delayed ) {
				list( $all, $open, $body, $close ) = $m;
				$type = strtolower( trim( (string) self::attr( $open, 'type' ) ) );
				if ( '' !== $type && ! in_array( $type, array( 'text/javascript', 'application/javascript', 'module', 'text/ecmascript' ), true ) ) {
					return $all; // JSON-LD, templates, import maps…
				}
				if ( self::has_attr( $open, 'data-slipstream-skip' ) || self::has_attr( $open, 'data-no-optimize' ) ) {
					return $all;
				}
				$src = self::attr( $open, 'src' );
				$id  = (string) self::attr( $open, 'id' );
				$key = null !== $src ? $src : $body . ' ' . $id;

				if ( $s['delay_js'] && ! self::matches( $key, (array) $s['delay_exclude'] ) ) {
					$delayed++;
					$new = preg_replace( '/\stype\s*=\s*(["\']).*?\1/i', '', $open );
					$new = preg_replace( '/^<script/i', '<script type="slipstream/delay"' . ( '' !== $type ? ' data-type="' . esc_attr( $type ) . '"' : '' ), $new );
					if ( null !== $src ) {
						$new = preg_replace( '/\ssrc\s*=/i', ' data-slipstream-src=', $new );
					}
					return $new . $body . $close;
				}
				if ( ! $s['defer_js'] || 'module' === $type || self::has_attr( $open, 'async' ) || self::has_attr( $open, 'nomodule' ) ) {
					return $all;
				}
				if ( null !== $src ) {
					if ( self::has_attr( $open, 'defer' ) ) {
						$deferring = true;
						return $all;
					}
					if ( self::has_attr( $open, 'data-no-defer' ) || self::matches( $src, array_merge( self::DEFER_SAFE, (array) $s['defer_exclude'] ) ) ) {
						return $all;
					}
					$deferring = true;
					return preg_replace( '/^<script/i', '<script defer', $open ) . $body . $close;
				}
				// Inline code after a deferred script waits for it, keeping the original order.
				if ( $deferring && '' !== trim( $body ) && ! self::matches( $body . ' ' . $id, (array) $s['defer_exclude'] ) ) {
					$attrs = preg_replace( '/^<script|>$/i', '', $open );
					return '<script defer' . $attrs . ' src="data:text/javascript;base64,' . base64_encode( $body ) . '">' . $close; // phpcs:ignore
				}
				return $all;
			},
			$html
		);
		if ( null === $out ) {
			return $html;
		}
		if ( $delayed ) {
			$out = self::inject_head( $out, '<script data-slipstream-skip>' . self::delay_loader( (int) $s['delay_timeout'] ) . '</script>' );
		}
		return $out;
	}

	/** Runs delayed scripts, in their original order, on the first interaction. */
	private static function delay_loader( $timeout ) {
		$js = <<<'JS'
(function(){var d=document,w=window,done=0,ev=['keydown','mousedown','mousemove','touchstart','touchmove','wheel','scroll'],o={passive:true};
function go(){if(done)return;done=1;ev.forEach(function(e){w.removeEventListener(e,go,o)});var l=[].slice.call(d.querySelectorAll('script[type="slipstream/delay"]')),i=0;
(function next(){if(i>=l.length){if(d.readyState!=='loading'){d.dispatchEvent(new Event('DOMContentLoaded',{bubbles:true}))}if(d.readyState==='complete'){w.dispatchEvent(new Event('load'))}return}
var a=l[i++],s=d.createElement('script'),src=a.getAttribute('data-slipstream-src');[].slice.call(a.attributes).forEach(function(t){if(['type','data-type','data-slipstream-src'].indexOf(t.name)<0)s.setAttribute(t.name,t.value)});
if(a.getAttribute('data-type'))s.type=a.getAttribute('data-type');if(src){s.onload=s.onerror=next;s.src=src;a.parentNode.replaceChild(s,a)}else{s.text=a.text;a.parentNode.replaceChild(s,a);next()}})()}
ev.forEach(function(e){w.addEventListener(e,go,o)});TIMEOUT})();
JS;
		return str_replace( 'TIMEOUT', $timeout > 0 ? 'setTimeout(go,' . ( $timeout * 1000 ) . ');' : '', $js );
	}

	/* ============================================================== media */

	private static function lazy( $html ) {
		$n = 0;
		$html = preg_replace_callback(
			'#<img\b[^>]*>#i',
			function ( $m ) use ( &$n ) {
				$tag = $m[0];
				$n++;
				// The first images are usually above the fold: loading them lazily would slow the page.
				if ( $n <= 2 || self::has_attr( $tag, 'loading' ) || self::has_attr( $tag, 'data-no-lazy' ) || false !== stripos( $tag, 'fetchpriority="high"' ) || false !== strpos( $tag, 'skip-lazy' ) ) {
					return $tag;
				}
				$add = ' loading="lazy"' . ( self::has_attr( $tag, 'decoding' ) ? '' : ' decoding="async"' );
				return preg_replace( '#\s*/?>$#', $add . '$0', $tag, 1 );
			},
			$html
		);
		return preg_replace_callback(
			'#<iframe\b[^>]*>#i',
			function ( $m ) {
				return self::has_attr( $m[0], 'loading' ) || self::has_attr( $m[0], 'data-no-lazy' ) ? $m[0] : preg_replace( '#>$#', ' loading="lazy">', $m[0], 1 );
			},
			$html
		);
	}

	/** Replace YouTube players with a thumbnail; the real player loads on click (saves ~1 MB). */
	private static function youtube( $html ) {
		$used = false;
		$html = preg_replace_callback(
			'#<iframe\b[^>]*\ssrc=(["\'])(?:https?:)?//(?:www\.)?(?:youtube\.com|youtube-nocookie\.com)/embed/([A-Za-z0-9_\-]{11})([^"\']*)\1[^>]*>\s*</iframe>#i',
			function ( $m ) use ( &$used ) {
				$used  = true;
				$id    = $m[2];
				$title = (string) self::attr( $m[0], 'title' );
				$sep   = false === strpos( $m[3], '?' ) ? '?' : '&';
				$frame = preg_replace( '#\ssrc=(["\']).*?\1#i', ' src="' . esc_url( 'https://www.youtube' . ( false !== stripos( $m[0], 'nocookie' ) ? '-nocookie' : '' ) . '.com/embed/' . $id . html_entity_decode( $m[3] ) . $sep . 'autoplay=1' ) . '"', $m[0], 1 );
				$frame = preg_replace( '#\sloading=(["\']).*?\1#i', '', $frame );
				$label = $title ? sprintf( /* translators: %s: video title */ __( 'Play video: %s', 'slipstream' ), $title ) : __( 'Play video', 'slipstream' );
				return '<div class="ss-yt"><template>' . $frame . '</template><img src="https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg" alt="' . esc_attr( $title ) . '" loading="lazy" decoding="async"><button type="button" class="ss-yt__play" aria-label="' . esc_attr( $label ) . '"></button></div>';
			},
			$html
		);
		if ( $used ) {
			$css  = '.ss-yt{position:relative;width:100%;aspect-ratio:16/9;background:#000;overflow:hidden;cursor:pointer}.wp-has-aspect-ratio .ss-yt{position:absolute;inset:0;aspect-ratio:auto}.ss-yt img{width:100%;height:100%;object-fit:cover;display:block;margin:0}.ss-yt__play{position:absolute;inset:0;margin:auto;width:68px;height:48px;border:0;border-radius:12px;background:rgba(23,23,23,.8);cursor:pointer}.ss-yt:hover .ss-yt__play{background:#f00}.ss-yt__play:before{content:"";position:absolute;left:27px;top:14px;border-style:solid;border-width:10px 0 10px 17px;border-color:transparent transparent transparent #fff}';
			$js   = 'document.addEventListener("click",function(e){var b=e.target.closest(".ss-yt");if(!b)return;var t=b.querySelector("template");if(t){b.outerHTML=t.innerHTML}});';
			$html = self::inject_head( $html, '<style id="slipstream-yt">' . $css . '</style>' );
			$html = preg_replace( '#</body>#i', '<script data-slipstream-skip>' . $js . '</script></body>', $html, 1 );
		}
		return $html;
	}

	/* ===================================================== hints, CDN, minify */

	private static function inject_head( $html, $snippet ) {
		$out = preg_replace( '#</head>#i', $snippet . '</head>', $html, 1, $n );
		return $n ? $out : $html;
	}

	private static function head_hints( $html, array $s ) {
		$links = '';
		foreach ( (array) $s['preconnect'] as $u ) {
			$p = wp_parse_url( $u );
			if ( ! empty( $p['host'] ) ) {
				$origin = ( isset( $p['scheme'] ) ? $p['scheme'] : 'https' ) . '://' . $p['host'];
				$links .= '<link rel="preconnect" href="' . esc_url( $origin ) . '" crossorigin><link rel="dns-prefetch" href="' . esc_url( $origin ) . '">';
			}
		}
		foreach ( (array) $s['preload_fonts'] as $u ) {
			$ext   = strtolower( pathinfo( (string) wp_parse_url( $u, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
			$type  = in_array( $ext, array( 'woff2', 'woff', 'ttf', 'otf' ), true ) ? ' type="font/' . ( 'ttf' === $ext ? 'ttf' : ( 'otf' === $ext ? 'otf' : $ext ) ) . '"' : '';
			$links .= '<link rel="preload" href="' . esc_url( $u ) . '" as="font"' . $type . ' crossorigin>';
		}
		if ( '' === $links ) {
			return $html;
		}
		// As early as possible: right after <head …>.
		$out = preg_replace( '#<head\b[^>]*>#i', '$0' . $links, $html, 1, $n );
		return $n ? $out : $html;
	}

	/** Point static files (images, CSS, JS, fonts) at the CDN address. */
	private static function cdn( $html, $cdn ) {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$base = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		if ( ! $host ) {
			return $html;
		}
		$pattern = '#(?:https?:)?//' . preg_quote( $host, '#' ) . '(' . preg_quote( untrailingslashit( $base ), '#' ) . '/(?:wp-content|wp-includes)/[^"\'\s)?]+\.(?:jpe?g|png|gif|webp|avif|svg|ico|css|js|woff2?|ttf|otf|eot|mp4|webm|pdf))#i';
		$out     = preg_replace( $pattern, $cdn . '$1', $html );
		return null === $out ? $html : $out;
	}

	/** Remove comments and runs of whitespace; one space or newline is always kept. */
	private static function minify( $html ) {
		$out = preg_replace( '#<!--(?!\s*\[|\s*<!|\s*noindex|\s*/?noindex).*?-->#s', '', $html );
		$out = null === $out ? $html : $out;
		$out = preg_replace( '#[ \t]*\r?\n\s*#', "\n", $out );
		$out = preg_replace( '#[ \t]{2,}#', ' ', $out );
		return null === $out ? $html : $out;
	}
}
