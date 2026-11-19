<?php
/**
 * Plugin Name:       Slipstream
 * Plugin URI:        https://www.prasatdigital.com
 * Description:       Make WordPress fast: page cache with automatic clearing and preloading, browser caching, HTML minify, deferred or delayed JavaScript, lazy media, YouTube click-to-load, CDN, database clean-up and switches for the WordPress features you don't use.
 * Version:           1.0.6
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Update URI:        https://github.com/thhyarith-prog/slipstream
 * Author:            Prasat Digital
 * Author URI:        https://www.prasatdigital.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       slipstream
 *
 * @package Slipstream
 */

defined( 'ABSPATH' ) || exit;

define( 'SLIPSTREAM_VERSION', '1.0.6' );
define( 'SLIPSTREAM_FILE', __FILE__ );
define( 'SLIPSTREAM_DIR', plugin_dir_path( __FILE__ ) );
define( 'SLIPSTREAM_URL', plugin_dir_url( __FILE__ ) );

// Updates come from the GitHub releases of this plugin.
require_once SLIPSTREAM_DIR . 'includes/prasat-updater.php';
Prasat_Updater::register( __FILE__, 'thhyarith-prog/slipstream' );

/*
 * Fallback serving: when the advanced-cache.php drop-in isn't active (another cache plugin owns
 * it, or wp-config.php is read-only), send cached pages as soon as this plugin loads.
 */
if ( ! defined( 'SLIPSTREAM_DROPIN' ) && is_file( WP_CONTENT_DIR . '/cache/slipstream-config.php' ) ) {
	$slipstream_config = include WP_CONTENT_DIR . '/cache/slipstream-config.php';
	if ( is_array( $slipstream_config ) && ! empty( $slipstream_config['enabled'] ) ) {
		require_once SLIPSTREAM_DIR . 'includes/class-slipstream-serve.php';
		Slipstream_Serve::serve( $slipstream_config );
	}
	unset( $slipstream_config );
}

foreach ( array( 'serve', 'util', 'settings', 'cache', 'preload', 'optimize', 'tweaks', 'database', 'htaccess' ) as $slipstream_file ) {
	require_once SLIPSTREAM_DIR . 'includes/class-slipstream-' . $slipstream_file . '.php';
}
unset( $slipstream_file );
if ( is_admin() || wp_doing_ajax() ) {
	require_once SLIPSTREAM_DIR . 'includes/class-slipstream-admin.php';
	require_once SLIPSTREAM_DIR . 'includes/prasat-hub.php';
	Prasat_Hub::register( 'slipstream', array( 'assets' => SLIPSTREAM_URL . 'assets/', 'health' => array( 'Slipstream_Admin', 'health' ), 'dashboard' => array( 'Slipstream_Admin', 'dashboard' ) ) );
}

final class Slipstream {

	public static function init() {
		Slipstream_Cache::init();
		Slipstream_Preload::init();
		Slipstream_Tweaks::init();
		Slipstream_Database::init();
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( 'init', array( __CLASS__, 'maintain' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 90 );
		add_action( 'admin_post_slipstream_clear', array( __CLASS__, 'post_clear' ) );
		add_action( 'slipstream_settings_updated', array( __CLASS__, 'apply_settings' ) );
		if ( class_exists( 'Slipstream_Admin' ) ) {
			Slipstream_Admin::init();
		}
	}

	public static function cron_schedules( $s ) {
		$s['slipstream_monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => 'Once a month (Slipstream)',
		);
		return $s;
	}

	/** Keep the drop-in config and the hourly clean-up in place (e.g. after a migration). */
	public static function maintain() {
		if ( ! wp_next_scheduled( 'slipstream_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'slipstream_cleanup' );
		}
		if ( get_option( 'slipstream_version' ) !== SLIPSTREAM_VERSION || ! is_file( Slipstream_Cache::config_file() ) ) {
			Slipstream_Cache::install();
			update_option( 'slipstream_version', SLIPSTREAM_VERSION, false );
		}
	}

	public static function activate() {
		if ( false === get_option( Slipstream_Settings::OPTION ) ) {
			add_option( Slipstream_Settings::OPTION, Slipstream_Settings::defaults(), '', true );
		}
		Slipstream_Cache::install();
		if ( Slipstream_Settings::get( 'browser_cache' ) ) {
			Slipstream_Htaccess::apply( true );
		}
		Slipstream_Database::reschedule();
		update_option( 'slipstream_version', SLIPSTREAM_VERSION, false );
		set_transient( 'slipstream_welcome', 1, HOUR_IN_SECONDS );
		if ( Slipstream_Settings::get( 'preload' ) ) {
			Slipstream_Preload::queue();
		}
	}

	public static function deactivate() {
		Slipstream_Cache::uninstall();
		Slipstream_Htaccess::apply( false );
		Slipstream_Preload::cancel();
		foreach ( array( 'slipstream_cleanup', Slipstream_Database::HOOK ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		delete_transient( 'slipstream_stats' );
	}

	/** After settings change: rewrite the drop-in config, .htaccess rules and schedules. */
	public static function apply_settings( $s ) {
		Slipstream_Cache::write_config();
		Slipstream_Htaccess::apply( ! empty( $s['browser_cache'] ) );
	}

	/** "Slipstream" in the admin bar: clear everything, or just the page you're looking at. */
	public static function admin_bar( $bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$base = admin_url( 'admin-post.php?action=slipstream_clear' );
		$bar->add_node(
			array(
				'id'    => 'slipstream',
				'title' => '<span class="ab-icon dashicons dashicons-performance" style="top:2px"></span><span class="ab-label">Slipstream</span>',
				'href'  => admin_url( 'admin.php?page=slipstream' ),
			)
		);
		$bar->add_node(
			array(
				'parent' => 'slipstream',
				'id'     => 'slipstream-clear-all',
				'title'  => __( 'Clear all cache', 'slipstream' ),
				'href'   => wp_nonce_url( $base . '&what=all', 'slipstream_clear' ),
			)
		);
		if ( ! is_admin() ) {
			$here = home_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/' );
			$bar->add_node(
				array(
					'parent' => 'slipstream',
					'id'     => 'slipstream-clear-page',
					'title'  => __( 'Clear this page', 'slipstream' ),
					'href'   => wp_nonce_url( $base . '&what=url&url=' . rawurlencode( $here ), 'slipstream_clear' ),
				)
			);
		}
		$bar->add_node(
			array(
				'parent' => 'slipstream',
				'id'     => 'slipstream-preload',
				'title'  => __( 'Clear and preload the whole site', 'slipstream' ),
				'href'   => wp_nonce_url( $base . '&what=preload', 'slipstream_clear' ),
			)
		);
	}

	public static function post_clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'slipstream' ), 403 );
		}
		check_admin_referer( 'slipstream_clear' );
		$what = isset( $_GET['what'] ) ? sanitize_key( $_GET['what'] ) : 'all';
		if ( 'url' === $what && ! empty( $_GET['url'] ) ) {
			$url = esc_url_raw( wp_unslash( $_GET['url'] ) );
			Slipstream_Cache::purge_url( $url );
			wp_safe_redirect( $url );
			exit;
		}
		Slipstream_Cache::purge_all();
		if ( 'preload' === $what || Slipstream_Settings::get( 'preload' ) ) {
			Slipstream_Preload::queue();
		}
		$back = wp_get_referer();
		wp_safe_redirect( add_query_arg( 'ss_msg', 'preload' === $what ? 'preload' : 'cleared', $back ? $back : admin_url( 'admin.php?page=slipstream' ) ) );
		exit;
	}
}

register_activation_hook( __FILE__, array( 'Slipstream', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Slipstream', 'deactivate' ) );
Slipstream::init();
