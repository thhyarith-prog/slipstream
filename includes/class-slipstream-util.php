<?php
/**
 * Small helpers.
 *
 * @package Slipstream
 */

defined( 'ABSPATH' ) || exit;

class Slipstream_Util {

	/** Keep a cache folder from being listed by the web server. */
	public static function protect_dir( $dir ) {
		if ( ! wp_mkdir_p( $dir ) ) {
			return;
		}
		if ( ! is_file( $dir . '/index.php' ) ) {
			@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
		}
	}

	/**
	 * Delete a folder tree. With $keep_root the folder itself stays (emptied).
	 * Only ever used inside wp-content/cache/slipstream.
	 */
	public static function rrmdir( $dir, $keep_root = false ) {
		$dir  = untrailingslashit( wp_normalize_path( $dir ) );
		$root = wp_normalize_path( WP_CONTENT_DIR . '/cache/slipstream' );
		if ( $dir !== $root && 0 !== strpos( $dir, $root . '/' ) ) {
			return; // Never delete anything outside our cache folder.
		}
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			if ( $f->isDir() && ! $f->isLink() ) {
				@rmdir( $f->getPathname() ); // phpcs:ignore
			} else {
				@unlink( $f->getPathname() ); // phpcs:ignore
			}
		}
		if ( ! $keep_root ) {
			@rmdir( $dir ); // phpcs:ignore
		}
	}

	public static function bytes( $n ) {
		return size_format( max( 0, (float) $n ), $n >= 1048576 ? 1 : 0 );
	}

	/** Apache (or LiteSpeed) reads .htaccess; Nginx and others don't. */
	public static function server() {
		$s = isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) ) : '';
		if ( false !== strpos( $s, 'litespeed' ) ) {
			return 'litespeed';
		}
		if ( false !== strpos( $s, 'apache' ) ) {
			return 'apache';
		}
		if ( false !== strpos( $s, 'nginx' ) ) {
			return 'nginx';
		}
		return $s ? 'other' : 'unknown';
	}
}
