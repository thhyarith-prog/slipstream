<?php
/**
 * Browser caching and compression rules for Apache / LiteSpeed (.htaccess). Other servers get
 * the equivalent snippet to paste. Rules live between "# BEGIN Slipstream" markers, so they are
 * updated or removed cleanly and nothing else in the file is touched.
 *
 * @package Slipstream
 */

defined( 'ABSPATH' ) || exit;

class Slipstream_Htaccess {

	const MARKER = 'Slipstream';

	public static function file() {
		return get_home_path() . '.htaccess';
	}

	public static function supported() {
		return in_array( Slipstream_Util::server(), array( 'apache', 'litespeed' ), true );
	}

	public static function rules() {
		return array(
			'<IfModule mod_expires.c>',
			'ExpiresActive On',
			'ExpiresDefault "access plus 1 month"',
			'ExpiresByType text/html "access plus 0 seconds"',
			'ExpiresByType text/css "access plus 1 year"',
			'ExpiresByType application/javascript "access plus 1 year"',
			'ExpiresByType text/javascript "access plus 1 year"',
			'ExpiresByType image/webp "access plus 1 year"',
			'ExpiresByType image/avif "access plus 1 year"',
			'ExpiresByType image/jpeg "access plus 1 year"',
			'ExpiresByType image/png "access plus 1 year"',
			'ExpiresByType image/gif "access plus 1 year"',
			'ExpiresByType image/svg+xml "access plus 1 year"',
			'ExpiresByType image/x-icon "access plus 1 year"',
			'ExpiresByType font/woff2 "access plus 1 year"',
			'ExpiresByType font/woff "access plus 1 year"',
			'ExpiresByType video/mp4 "access plus 1 year"',
			'ExpiresByType video/webm "access plus 1 year"',
			'</IfModule>',
			'<IfModule mod_deflate.c>',
			'AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml application/xml application/javascript text/javascript application/json image/svg+xml font/ttf font/otf',
			'</IfModule>',
			'<IfModule mod_headers.c>',
			'<FilesMatch "\.(css|js|webp|avif|jpe?g|png|gif|svg|ico|woff2?|mp4|webm)$">',
			'Header set Cache-Control "public, max-age=31536000"',
			'</FilesMatch>',
			'</IfModule>',
		);
	}

	public static function nginx_snippet() {
		return "gzip on;\ngzip_types text/plain text/css application/javascript application/json image/svg+xml;\nlocation ~* \\.(css|js|webp|avif|jpe?g|png|gif|svg|ico|woff2?|mp4|webm)$ {\n    expires 1y;\n    add_header Cache-Control \"public, max-age=31536000\";\n}";
	}

	/** Write or remove the rules. Returns true when the file now matches the setting. */
	public static function apply( $on ) {
		if ( ! self::supported() ) {
			return false;
		}
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$file = self::file();
		if ( ! file_exists( $file ) && ! $on ) {
			return true;
		}
		if ( ( file_exists( $file ) && ! is_writable( $file ) ) || ( ! file_exists( $file ) && ! is_writable( dirname( $file ) ) ) ) {
			return false;
		}
		return insert_with_markers( $file, self::MARKER, $on ? self::rules() : array() );
	}

	public static function present() {
		if ( ! function_exists( 'extract_from_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		return file_exists( self::file() ) && (bool) extract_from_markers( self::file(), self::MARKER );
	}
}
