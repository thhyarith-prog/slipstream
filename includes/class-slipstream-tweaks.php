<?php
/**
 * Switch off WordPress features most sites don't use, so pages ship less code.
 *
 * @package Slipstream
 */

defined( 'ABSPATH' ) || exit;

class Slipstream_Tweaks {

	public static function init() {
		$s = Slipstream_Settings::all();
		if ( $s['no_emoji'] ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
			remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			add_filter( 'emoji_svg_url', '__return_false' );
			add_filter(
				'wp_resource_hints',
				function ( $urls, $type ) {
					return 'dns-prefetch' === $type ? array_filter( $urls, function ( $u ) { return false === strpos( is_array( $u ) ? (string) ( isset( $u['href'] ) ? $u['href'] : '' ) : (string) $u, 's.w.org/images/core/emoji' ); } ) : $urls;
				},
				10,
				2
			);
		}
		if ( $s['no_embeds'] ) {
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
			remove_action( 'wp_head', 'wp_oembed_add_host_js' );
			add_action(
				'wp_footer',
				function () {
					wp_dequeue_script( 'wp-embed' );
				}
			);
		}
		if ( $s['no_jquery_migrate'] ) {
			add_action(
				'wp_default_scripts',
				function ( $scripts ) {
					if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
						$scripts->registered['jquery']->deps = array_diff( $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) );
					}
				}
			);
		}
		if ( $s['no_dashicons'] ) {
			add_action(
				'wp_enqueue_scripts',
				function () {
					if ( ! is_user_logged_in() && ! is_admin_bar_showing() ) {
						wp_dequeue_style( 'dashicons' );
					}
				},
				100
			);
		}
		if ( $s['clean_head'] ) {
			remove_action( 'wp_head', 'rsd_link' );
			remove_action( 'wp_head', 'wlwmanifest_link' );
			remove_action( 'wp_head', 'wp_generator' );
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		}
		if ( $s['no_self_pings'] ) {
			add_action(
				'pre_ping',
				function ( &$links ) {
					$home = home_url();
					foreach ( $links as $i => $l ) {
						if ( 0 === strpos( $l, $home ) ) {
							unset( $links[ $i ] );
						}
					}
				}
			);
		}
		if ( 'default' !== $s['heartbeat'] ) {
			add_action( 'init', array( __CLASS__, 'heartbeat' ), 1 );
			add_filter( 'heartbeat_settings', array( __CLASS__, 'heartbeat_settings' ) );
		}
		if ( (int) $s['revisions'] >= 0 && ! self::revisions_in_config() ) {
			add_filter(
				'wp_revisions_to_keep',
				function ( $num ) use ( $s ) {
					return -1 === (int) $num || (int) $num > (int) $s['revisions'] ? (int) $s['revisions'] : $num;
				}
			);
		}
	}

	/** WordPress defines WP_POST_REVISIONS as true itself; only a number means the owner set it. */
	public static function revisions_in_config() {
		return defined( 'WP_POST_REVISIONS' ) && true !== WP_POST_REVISIONS;
	}

	/**
	 * Heartbeat: never on the public site; in the dashboard every 60 s ("reduce") or not at all
	 * ("disable"). The post editor always keeps it, because it saves drafts and post locks.
	 */
	public static function heartbeat() {
		global $pagenow;
		$editor = in_array( $pagenow, array( 'post.php', 'post-new.php' ), true );
		if ( ! is_admin() || ( 'disable' === Slipstream_Settings::get( 'heartbeat' ) && ! $editor ) ) {
			wp_deregister_script( 'heartbeat' );
		}
	}

	public static function heartbeat_settings( $settings ) {
		$settings['interval'] = 60;
		return $settings;
	}
}
