<?php
/**
 * Preload: visits pages in the background so the first real visitor gets a cached copy.
 * A queue of URLs is worked through a few at a time by WP-Cron, so it never slows the site.
 *
 * @package Slipstream
 */

defined( 'ABSPATH' ) || exit;

class Slipstream_Preload {

	const OPTION = 'slipstream_preload';
	const HOOK   = 'slipstream_preload_run';
	const BATCH  = 8;
	const MAX    = 500;

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	/** The pages worth warming: home, recent posts and pages, product and category pages. */
	public static function site_urls() {
		$urls  = array( home_url( '/' ) );
		$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
		$posts = get_posts(
			array(
				'post_type'        => $types,
				'post_status'      => 'publish',
				'posts_per_page'   => 300,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'fields'           => 'ids',
				'has_password'     => false,
				'suppress_filters' => false,
			)
		);
		foreach ( $posts as $id ) {
			$urls[] = get_permalink( $id );
		}
		$terms = get_terms(
			array(
				'taxonomy'   => array_values( get_taxonomies( array( 'public' => true ) ) ),
				'hide_empty' => true,
				'number'     => 100,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$link = get_term_link( $t );
				if ( ! is_wp_error( $link ) ) {
					$urls[] = $link;
				}
			}
		}
		return array_slice( array_values( array_unique( array_filter( $urls ) ) ), 0, self::MAX );
	}

	public static function state() {
		$s = get_option( self::OPTION );
		return is_array( $s ) ? $s + array( 'queue' => array(), 'done' => 0, 'total' => 0, 'started' => 0, 'finished' => 0 ) : array( 'queue' => array(), 'done' => 0, 'total' => 0, 'started' => 0, 'finished' => 0 );
	}

	/** Add URLs (default: the whole site) to the queue and make sure a worker is scheduled. */
	public static function queue( ?array $urls = null ) {
		if ( ! Slipstream_Settings::get( 'cache' ) ) {
			return;
		}
		$all = null === $urls;
		$urls = $all ? self::site_urls() : $urls;
		$s    = self::state();
		if ( $all || empty( $s['queue'] ) ) {
			$s['done']     = 0;
			$s['total']    = 0;
			$s['started']  = time();
			$s['finished'] = 0;
		}
		$before     = count( $s['queue'] );
		$s['queue'] = array_slice( array_values( array_unique( array_merge( $s['queue'], array_map( 'esc_url_raw', $urls ) ) ) ), 0, self::MAX );
		$s['total'] += count( $s['queue'] ) - $before;
		update_option( self::OPTION, $s, false );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::HOOK );
		}
	}

	public static function cancel() {
		delete_option( self::OPTION );
		wp_clear_scheduled_hook( self::HOOK );
	}

	/** Cron worker: warm a batch, then schedule the next one. */
	public static function run() {
		if ( get_transient( 'slipstream_preload_lock' ) ) {
			return;
		}
		set_transient( 'slipstream_preload_lock', 1, 120 );
		$s     = self::state();
		$batch = array_splice( $s['queue'], 0, self::BATCH );
		update_option( self::OPTION, $s, false ); // Claim them first, so a crash can't loop on one URL.
		foreach ( $batch as $url ) {
			self::warm( $url );
			$s['done']++;
		}
		$fresh = self::state(); // URLs may have been queued while we worked.
		$fresh['done']  = $s['done'];
		$fresh['total'] = max( $fresh['total'], $s['done'] + count( $fresh['queue'] ) );
		if ( empty( $fresh['queue'] ) ) {
			$fresh['finished'] = time();
		} else {
			wp_schedule_single_event( time() + 10, self::HOOK );
		}
		update_option( self::OPTION, $fresh, false );
		delete_transient( 'slipstream_preload_lock' );
	}

	public static function warm( $url ) {
		$args = array(
			'timeout'     => 15,
			'redirection' => 0,
			'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			'user-agent'  => 'Mozilla/5.0 (compatible; SlipstreamPreload/' . SLIPSTREAM_VERSION . '; +' . home_url( '/' ) . ')',
			'headers'     => array( 'Accept' => 'text/html' ),
		);
		wp_remote_get( $url, $args );
		if ( Slipstream_Settings::get( 'mobile_cache' ) ) {
			$args['user-agent'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 SlipstreamPreload/' . SLIPSTREAM_VERSION;
			wp_remote_get( $url, $args );
		}
	}
}
