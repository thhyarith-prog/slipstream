<?php
/**
 * Page cache lookups that run before WordPress loads (from advanced-cache.php), so this file
 * must not use any WordPress function. The same rules decide what may be stored, so a page is
 * only ever served to the kind of visitor it was made for.
 *
 * Config array (written by Slipstream_Cache::write_config()):
 *   enabled, dir, lifespan, mobile, ignore_query[], exclude_paths[], exclude_cookies[], exclude_agents[]
 *
 * @package Slipstream
 */

if ( ! class_exists( 'Slipstream_Serve' ) ) {

	class Slipstream_Serve {

		/** Cookies that mean "this visitor sees a personal page". */
		const PERSONAL_COOKIES = array( 'wordpress_logged_in_', 'wp-postpass_', 'comment_author_', 'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session_', 'edd_items_in_cart', 'slipstream_bypass' );

		const MOBILE = '/Mobile|Android|Silk\/|Kindle|BlackBerry|Opera Mini|Opera Mobi|iPhone|iPod|webOS|IEMobile/i';

		/**
		 * Can this request use (or fill) the cache? Returns false or the reason it can't.
		 *
		 * @return true|string
		 */
		public static function cacheable_request( array $c ) {
			if ( empty( $c['enabled'] ) ) {
				return 'off';
			}
			$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
			if ( 'GET' !== $method && 'HEAD' !== $method ) {
				return 'method';
			}
			if ( ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || 'cli' === PHP_SAPI ) {
				return 'context';
			}
			$path = self::path_part();
			if ( null === $path ) {
				return 'path';
			}
			if ( preg_match( '#(^|/)(wp-admin|wp-login\.php|wp-json|xmlrpc\.php|wp-cron\.php|wp-comments-post\.php|wp-signup\.php|wp-activate\.php|wp-trackback\.php)(/|$)#i', $path ) || preg_match( '#\.(php|xml|txt|xsl|json)$#i', $path ) ) {
				return 'path';
			}
			if ( ! empty( $_SERVER['QUERY_STRING'] ) && null === self::clean_query( $c ) ) {
				return 'query';
			}
			foreach ( array_keys( $_COOKIE ) as $name ) {
				foreach ( array_merge( self::PERSONAL_COOKIES, (array) $c['exclude_cookies'] ) as $prefix ) {
					if ( '' !== $prefix && 0 === stripos( (string) $name, $prefix ) ) {
						return 'cookie';
					}
				}
			}
			foreach ( (array) $c['exclude_paths'] as $p ) {
				$p = trim( (string) $p );
				if ( '' === $p ) {
					continue;
				}
				if ( false !== strpos( $p, '*' ) ? fnmatch( '/' . ltrim( $p, '/' ), $path ) : ( 0 === stripos( $path, '/' . trim( $p, '/' ) . '/' ) || rtrim( $path, '/' ) === '/' . trim( $p, '/' ) ) ) {
					return 'excluded';
				}
			}
			$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
			foreach ( (array) $c['exclude_agents'] as $a ) {
				if ( '' !== trim( $a ) && false !== stripos( $ua, trim( $a ) ) ) {
					return 'agent';
				}
			}
			return true;
		}

		/** Request path without the query string, or null when it looks unsafe. */
		public static function path_part() {
			$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
			$path = (string) strtok( $uri, '?#' );
			$path = rawurldecode( $path );
			if ( '' === $path || '/' !== $path[0] || false !== strpos( $path, '..' ) || false !== strpos( $path, "\0" ) || strlen( $path ) > 600 ) {
				return null;
			}
			return $path;
		}

		/**
		 * Tracking parameters (utm_*, fbclid…) don't change the page, so they are ignored.
		 * Returns the remaining query ('' when only ignored ones were present) or null if the
		 * request has other parameters and must not be cached.
		 */
		public static function clean_query( array $c ) {
			parse_str( isset( $_SERVER['QUERY_STRING'] ) ? (string) $_SERVER['QUERY_STRING'] : '', $q );
			foreach ( array_keys( $q ) as $k ) {
				foreach ( (array) $c['ignore_query'] as $ig ) {
					$ig = trim( (string) $ig );
					if ( '' !== $ig && ( $k === $ig || ( '*' === substr( $ig, -1 ) && 0 === strpos( $k, substr( $ig, 0, -1 ) ) ) ) ) {
						unset( $q[ $k ] );
						continue 2;
					}
				}
			}
			return $q ? null : '';
		}

		public static function host() {
			$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( (string) $_SERVER['HTTP_HOST'] ) : '';
			return preg_match( '/^[a-z0-9.\-]+(:\d+)?$/', $host ) ? str_replace( ':', '_', $host ) : '';
		}

		public static function https() {
			return ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== strtolower( (string) $_SERVER['HTTPS'] ) ) || ( isset( $_SERVER['SERVER_PORT'] ) && 443 === (int) $_SERVER['SERVER_PORT'] ) || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( (string) $_SERVER['HTTP_X_FORWARDED_PROTO'] ) );
		}

		/** Absolute file name (without extension) of this request's cached page. */
		public static function file( array $c ) {
			$host = self::host();
			$path = self::path_part();
			if ( '' === $host || null === $path ) {
				return '';
			}
			$path = trim( $path, '/' );
			if ( '' !== $path && preg_match( '#[^A-Za-z0-9_\-./~@+]#', $path ) ) {
				$path = '_h/' . md5( $path ); // Non-ASCII (e.g. Khmer slugs) or unusual characters: a safe, unique name.
			}
			$path = '' === $path ? '' : '/' . $path;
			$name = 'index' . ( self::https() ? '-https' : '' );
			if ( ! empty( $c['mobile'] ) && preg_match( self::MOBILE, isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '' ) ) {
				$name .= '-mobile';
			}
			return rtrim( $c['dir'], '/' ) . '/' . $host . $path . '/' . $name;
		}

		/** Send the cached page and exit, or return quietly so WordPress builds it. */
		public static function serve( array $c ) {
			if ( true !== self::cacheable_request( $c ) ) {
				return;
			}
			$file = self::file( $c );
			if ( '' === $file ) {
				return;
			}
			$html = $file . '.html';
			$mt   = @filemtime( $html ); // phpcs:ignore
			if ( ! $mt || ( ! empty( $c['lifespan'] ) && $mt < time() - (int) $c['lifespan'] ) ) {
				return;
			}
			$head = isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] );
			$gz   = $file . '.html.gz';
			$use_gz = function_exists( 'ini_get' ) && ! ini_get( 'zlib.output_compression' ) && isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) && false !== stripos( (string) $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' ) && is_readable( $gz );
			header( 'Content-Type: text/html; charset=UTF-8' );
			header( 'X-Slipstream: HIT' );
			header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
			header( 'Vary: Accept-Encoding, Cookie' );
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $mt ) . ' GMT' );
			if ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) && strtotime( (string) $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) >= $mt ) {
				http_response_code( 304 );
				exit;
			}
			if ( $use_gz ) {
				header( 'Content-Encoding: gzip' );
				header( 'Content-Length: ' . filesize( $gz ) );
				if ( ! $head ) {
					readfile( $gz );
				}
			} else {
				header( 'Content-Length: ' . filesize( $html ) );
				if ( ! $head ) {
					readfile( $html );
				}
			}
			exit;
		}
	}
}
