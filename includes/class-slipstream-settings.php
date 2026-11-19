<?php
/**
 * Settings. Safe features are on by default; the ones that can break a theme (defer / delay
 * JavaScript, CDN) start off and are switched on by the site owner.
 *
 * @package Slipstream
 */

defined( 'ABSPATH' ) || exit;

class Slipstream_Settings {

	const OPTION = 'slipstream_settings';

	/** @var array|null */
	private static $cache;

	public static function defaults() {
		return array(
			// Cache.
			'cache'            => true,
			'lifespan_hours'   => 10,
			'mobile_cache'     => false,
			'preload'          => true,
			'browser_cache'    => true,
			'exclude_paths'    => array( 'cart', 'checkout', 'my-account', 'basket', 'wishlist' ),
			'exclude_cookies'  => array(),
			'exclude_agents'   => array(),
			'ignore_query'     => array( 'utm_*', 'fbclid', 'gclid', 'gbraid', 'wbraid', 'msclkid', 'mc_cid', 'mc_eid', '_ga', '_gl', 'ref' ),
			// Optimize.
			'minify_html'      => true,
			'defer_js'         => false,
			'defer_exclude'    => array(),
			'delay_js'         => false,
			'delay_exclude'    => array(),
			'delay_timeout'    => 0,
			'lazy_media'       => true,
			'youtube_facade'   => true,
			'preconnect'       => array(),
			'preload_fonts'    => array(),
			'cdn_url'          => '',
			'optimize_logged_in' => false,
			// Tweaks.
			'no_emoji'         => true,
			'no_embeds'        => true,
			'no_jquery_migrate' => false,
			'no_dashicons'     => true,
			'clean_head'       => true,
			'no_self_pings'    => true,
			'heartbeat'        => 'reduce',
			'revisions'        => 10,
			// Database.
			'db_schedule'      => 'off',
			'db_items'         => array( 'revisions', 'auto_drafts', 'trash_posts', 'spam_comments', 'trash_comments', 'transients' ),
		);
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		}
		return self::$cache;
	}

	public static function get( $key ) {
		$a = self::all();
		return isset( $a[ $key ] ) ? $a[ $key ] : null;
	}

	public static function update( array $values ) {
		$all = self::all();
		$d   = self::defaults();
		foreach ( $values as $k => $v ) {
			if ( array_key_exists( $k, $d ) ) {
				$all[ $k ] = self::sanitize( $k, $v, $d[ $k ] );
			}
		}
		update_option( self::OPTION, $all, true );
		self::$cache = $all;
		do_action( 'slipstream_settings_updated', $all );
		return $all;
	}

	private static function lines( $v ) {
		$lines = is_array( $v ) ? $v : preg_split( '/[\r\n,]+/', (string) $v );
		return array_values( array_unique( array_filter( array_map( 'trim', array_map( 'sanitize_text_field', $lines ) ), 'strlen' ) ) );
	}

	private static function sanitize( $key, $v, $default ) {
		switch ( $key ) {
			case 'lifespan_hours':
				return max( 0, min( 720, (int) $v ) );
			case 'delay_timeout':
				return max( 0, min( 60, (int) $v ) );
			case 'revisions':
				return max( -1, min( 100, (int) $v ) );
			case 'heartbeat':
				return in_array( $v, array( 'default', 'reduce', 'disable' ), true ) ? $v : 'reduce';
			case 'db_schedule':
				return in_array( $v, array( 'off', 'weekly', 'monthly' ), true ) ? $v : 'off';
			case 'db_items':
				return array_values( array_intersect( array_keys( Slipstream_Database::items() ), (array) $v ) );
			case 'cdn_url':
				$v = esc_url_raw( trim( (string) $v ), array( 'http', 'https' ) );
				return $v ? untrailingslashit( $v ) : '';
			case 'preconnect':
			case 'preload_fonts':
				return array_values( array_filter( array_map( 'esc_url_raw', self::lines( $v ) ) ) );
			case 'exclude_paths':
			case 'exclude_cookies':
			case 'exclude_agents':
			case 'ignore_query':
			case 'defer_exclude':
			case 'delay_exclude':
				return self::lines( $v );
			default:
				return is_bool( $default ) ? (bool) $v : $v;
		}
	}
}
