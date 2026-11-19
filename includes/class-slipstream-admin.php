<?php
/**
 * Admin screens (Prasat Digital UI kit).
 *
 * @package Slipstream
 */

defined( 'ABSPATH' ) || exit;

class Slipstream_Admin {

	const CAP = 'manage_options';

	/** Boolean settings per tab (unchecked switches are not posted). */
	const SWITCHES = array(
		'cache'    => array( 'cache', 'mobile_cache', 'preload', 'browser_cache' ),
		'optimize' => array( 'minify_html', 'defer_js', 'delay_js', 'lazy_media', 'youtube_facade', 'optimize_logged_in' ),
		'tweaks'   => array( 'no_emoji', 'no_embeds', 'no_jquery_migrate', 'no_dashicons', 'clean_head', 'no_self_pings' ),
		'database' => array(),
	);

	const FIELDS = array(
		'cache'    => array( 'lifespan_hours', 'exclude_paths', 'exclude_cookies', 'exclude_agents', 'ignore_query' ),
		'optimize' => array( 'defer_exclude', 'delay_exclude', 'delay_timeout', 'preconnect', 'preload_fonts', 'cdn_url' ),
		'tweaks'   => array( 'heartbeat', 'revisions' ),
		'database' => array( 'db_schedule', 'db_items' ),
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_post_slipstream_save', array( __CLASS__, 'post_save' ) );
		add_action( 'admin_post_slipstream_db_clean', array( __CLASS__, 'post_db_clean' ) );
		add_action( 'wp_ajax_slipstream_speed', array( __CLASS__, 'ajax_speed' ) );
		add_action( 'wp_ajax_slipstream_status', array( __CLASS__, 'ajax_status' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SLIPSTREAM_FILE ), array( __CLASS__, 'links' ) );
	}

	public static function menu() {
		add_submenu_page( Prasat_Hub::SLUG, 'Slipstream', 'Slipstream', self::CAP, 'slipstream', array( __CLASS__, 'render' ) );
	}

	public static function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'slipstream' ) . '</a>' );
		return $links;
	}

	public static function tabs() {
		return array(
			'dashboard' => __( 'Dashboard', 'slipstream' ),
			'cache'     => __( 'Cache', 'slipstream' ),
			'optimize'  => __( 'Optimize', 'slipstream' ),
			'database'  => __( 'Database', 'slipstream' ),
			'tweaks'    => __( 'Tweaks', 'slipstream' ),
		);
	}

	public static function url( $tab = 'dashboard', array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'slipstream', 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	private static function on_page() {
		return isset( $_GET['page'] ) && 'slipstream' === $_GET['page']; // phpcs:ignore
	}

	public static function body_class( $c ) {
		return self::on_page() ? $c . ' pd-body' : $c;
	}

	private static function ver( $f ) {
		return SLIPSTREAM_VERSION . '.' . (int) @filemtime( SLIPSTREAM_DIR . $f ); // phpcs:ignore
	}

	public static function assets() {
		if ( ! self::on_page() ) {
			return;
		}
		wp_enqueue_style( 'slipstream-ui', SLIPSTREAM_URL . 'assets/prasat-ui.css', array(), self::ver( 'assets/prasat-ui.css' ) );
		wp_enqueue_style( 'slipstream-admin', SLIPSTREAM_URL . 'assets/admin.css', array( 'slipstream-ui' ), self::ver( 'assets/admin.css' ) );
		wp_enqueue_script( 'slipstream-ui', SLIPSTREAM_URL . 'assets/prasat-ui.js', array(), self::ver( 'assets/prasat-ui.js' ), true );
		wp_enqueue_script( 'slipstream-admin', SLIPSTREAM_URL . 'assets/admin.js', array( 'slipstream-ui' ), self::ver( 'assets/admin.js' ), true );
		wp_localize_script(
			'slipstream-admin',
			'Slipstream',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'slipstream' ),
				'i18n'  => array(
					'testing'   => __( 'Measuring…', 'slipstream' ),
					'faster'    => __( '%s× faster', 'slipstream' ),
					'error'     => __( 'Could not reach the home page from the server.', 'slipstream' ),
					'notCached' => __( 'The home page was not cached. It may be excluded, or a plugin may be setting cookies.', 'slipstream' ),
					'justNow'   => __( 'Just now', 'slipstream' ),
					'expired'   => __( 'Your session has expired. Reload the page and try again.', 'slipstream' ),
					'running'   => __( 'Running in the background', 'slipstream' ),
					'finished'  => __( 'Finished just now', 'slipstream' ),
				),
			)
		);
	}

	/* ================================================================ render */

	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'slipstream' ) );
		}
		delete_transient( 'slipstream_welcome' );
		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard'; // phpcs:ignore
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'dashboard';
		$s    = Slipstream_Settings::all();

		echo '<div class="wrap pd-app slipstream">';
		echo '<header class="pd-brand"><div class="pd-brand__id"><span class="pd-brand__mark pd-brand__mark--icon" aria-hidden="true"><img src="' . esc_url( SLIPSTREAM_URL . 'assets/img/icon-slipstream.png' ) . '" alt="" width="48" height="48"></span><div><h1>Slipstream</h1><p class="pd-brand__tag">' . esc_html__( 'Cache · Optimize · Speed', 'slipstream' ) . '</p></div></div><div class="pd-brand__side">'; // phpcs:ignore -- fixed SVG.
		self::header_status();
		printf(
			'<a class="pd-credit" href="%1$s" target="_blank" rel="noopener noreferrer" title="prasatdigital.com"><img src="%2$s" alt="" width="20" height="20"><span>%3$s</span></a>',
			esc_url( 'https://www.prasatdigital.com' ),
			esc_url( SLIPSTREAM_URL . 'assets/img/prasat-mark-white.svg' ),
			sprintf( /* translators: %s: Prasat Digital */ esc_html__( 'by %s', 'slipstream' ), '<b>Prasat Digital</b>' )
		);
		echo '</div></header><nav class="pd-tabs" aria-label="' . esc_attr__( 'Slipstream sections', 'slipstream' ) . '">';
		foreach ( $tabs as $k => $label ) {
			printf( '<a href="%s" class="%s"%s>%s</a>', esc_url( self::url( $k ) ), $k === $tab ? 'is-active' : '', $k === $tab ? ' aria-current="page"' : '', esc_html( $label ) );
		}
		echo '</nav><hr class="wp-header-end">';
		include SLIPSTREAM_DIR . 'views/' . $tab . '.php';
		echo '</div>';
	}

	/** Slipstream's mark: three speed lines on the brand gradient tile. */

	private static function header_status() {
		$s = Slipstream_Settings::all();
		list( $pages ) = Slipstream_Cache::stats();
		if ( ! $s['cache'] ) {
			$dot  = 'off';
			$text = __( 'Cache off', 'slipstream' );
			$sub  = '';
		} else {
			$dot  = Slipstream_Cache::dropin_active() ? '' : 'warn';
			$text = Slipstream_Cache::dropin_active() ? __( 'Cache on', 'slipstream' ) : __( 'Cache on (standard mode)', 'slipstream' );
			/* translators: %s: number of pages */
			$sub = sprintf( _n( '%s page cached', '%s pages cached', $pages, 'slipstream' ), number_format_i18n( $pages ) );
		}
		printf( '<a class="pd-brand__status" href="%1$s"><span class="pd-dot%2$s" aria-hidden="true"></span>%3$s%4$s</a>', esc_url( self::url( 'cache' ) ), $dot ? ' pd-dot--' . esc_attr( $dot ) : '', esc_html( $text ), $sub ? ' <small>' . esc_html( $sub ) . '</small>' : '' );
	}

	/** A setting row with a switch. $more is shown under the text while the switch is on. */
	public static function switch_row( $key, $title, $desc, $badge = '', $more = '' ) {
		$on = (bool) Slipstream_Settings::get( $key );
		printf(
			'<div class="pd-row%1$s" data-row="%2$s"><div class="pd-row__text"><label class="pd-row__title" for="ss-%2$s">%3$s%4$s</label><p class="pd-row__desc">%5$s</p>%6$s</div><div class="pd-row__control"><span class="pd-switch"><input type="checkbox" role="switch" id="ss-%2$s" name="ss[%2$s]" value="1"%7$s><span></span></span></div></div>',
			$on ? '' : ' is-off',
			esc_attr( $key ),
			esc_html( $title ),
			$badge, // phpcs:ignore -- built by badge() below.
			esc_html( $desc ),
			$more ? '<div class="pd-row__more">' . $more . '</div>' : '', // phpcs:ignore -- built from escaped parts in the views.
			checked( $on, true, false )
		);
	}

	public static function badge( $text, $tone = '' ) {
		return ' <span class="pd-badge' . ( $tone ? ' pd-badge--' . esc_attr( $tone ) : '' ) . '">' . esc_html( $text ) . '</span>';
	}

	public static function textarea( $key, $label, $placeholder = '' ) {
		$v = (array) Slipstream_Settings::get( $key );
		return '<label class="pd-label" for="ss-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><textarea id="ss-' . esc_attr( $key ) . '" name="ss[' . esc_attr( $key ) . ']" rows="3" class="code" placeholder="' . esc_attr( $placeholder ) . '">' . esc_textarea( implode( "\n", $v ) ) . '</textarea>';
	}

	public static function form_start( $tab ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="pd-form"><input type="hidden" name="action" value="slipstream_save"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
		wp_nonce_field( 'slipstream_save' );
	}

	public static function form_end() {
		echo '<p class="pd-actions pd-save"><button class="button button-primary button-large">' . esc_html__( 'Save changes', 'slipstream' ) . '</button><span class="pd-muted">' . esc_html__( 'Saving clears the cache so every page picks up the change.', 'slipstream' ) . '</span></p></form>';
	}

	/* =============================================================== notices */

	public static function notices() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( get_transient( 'slipstream_welcome' ) && $screen && ! self::on_page() ) {
			printf( '<div class="notice notice-info is-dismissible"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>', esc_html__( 'Slipstream is on.', 'slipstream' ), esc_html__( 'Your pages are now cached and preloaded in the background.', 'slipstream' ), esc_url( self::url() ), esc_html__( 'Run a speed test →', 'slipstream' ) );
		}
		if ( ! self::on_page() ) {
			return;
		}
		if ( isset( $_GET['ss_msg'] ) ) { // phpcs:ignore
			$m = array(
				'saved'   => __( 'Saved. The cache was cleared so every page picks up the change.', 'slipstream' ),
				'cleared' => __( 'Cache cleared.', 'slipstream' ),
				'preload' => __( 'Cache cleared. Preloading the site in the background.', 'slipstream' ),
				'db'      => __( 'Database cleaned.', 'slipstream' ),
				'db_none' => __( 'Nothing needed cleaning.', 'slipstream' ),
			);
			$k = sanitize_key( $_GET['ss_msg'] ); // phpcs:ignore
			if ( isset( $m[ $k ] ) ) {
				printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $m[ $k ] ) );
			}
		}
	}

	/* ================================================================= forms */

	private static function guard_post( $nonce ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'slipstream' ), 403 );
		}
		check_admin_referer( $nonce );
	}

	public static function post_save() {
		self::guard_post( 'slipstream_save' );
		$tab = isset( $_POST['tab'] ) ? sanitize_key( $_POST['tab'] ) : '';
		if ( ! isset( self::SWITCHES[ $tab ] ) ) {
			wp_die( 'Unknown section.' );
		}
		$in   = isset( $_POST['ss'] ) && is_array( $_POST['ss'] ) ? wp_unslash( $_POST['ss'] ) : array(); // phpcs:ignore -- sanitized per key in Slipstream_Settings.
		$vals = array();
		foreach ( self::SWITCHES[ $tab ] as $k ) {
			$vals[ $k ] = ! empty( $in[ $k ] );
		}
		foreach ( self::FIELDS[ $tab ] as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$vals[ $k ] = $in[ $k ];
			} elseif ( 'db_items' === $k ) {
				$vals[ $k ] = array(); // No chip ticked.
			} // Any other missing field (e.g. disabled) keeps its saved value.
		}
		Slipstream_Settings::update( $vals );
		wp_safe_redirect( self::url( $tab, array( 'ss_msg' => 'saved' ) ) );
		exit;
	}

	public static function post_db_clean() {
		self::guard_post( 'slipstream_db_clean' );
		$items = isset( $_POST['items'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['items'] ) ) : array();
		$done = Slipstream_Database::clean( $items );
		wp_safe_redirect( self::url( 'database', array( 'ss_msg' => array_sum( $done ) ? 'db' : 'db_none' ) ) );
		exit;
	}

	/* ================================================================== ajax */

	private static function guard_ajax() {
		check_ajax_referer( 'slipstream', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'slipstream' ) ), 403 );
		}
	}

	private static function timed_get( $url, array $cookies = array() ) {
		$args = array(
			'timeout'     => 30,
			'redirection' => 2,
			'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			'user-agent'  => 'Mozilla/5.0 (compatible; SlipstreamSpeedTest/' . SLIPSTREAM_VERSION . ')',
			'headers'     => array( 'Accept-Encoding' => 'gzip' ),
			'cookies'     => $cookies,
		);
		$t = microtime( true );
		$r = wp_remote_get( $url, $args );
		$ms = (int) round( ( microtime( true ) - $t ) * 1000 );
		if ( is_wp_error( $r ) ) {
			return null;
		}
		return array(
			'ms'   => $ms,
			'code' => (int) wp_remote_retrieve_response_code( $r ),
			'hit'  => 'HIT' === wp_remote_retrieve_header( $r, 'x-slipstream' ),
			'size' => strlen( wp_remote_retrieve_body( $r ) ),
		);
	}

	/** Time the home page built by WordPress vs served by Slipstream. */
	public static function ajax_speed() {
		self::guard_ajax();
		$url = home_url( '/' );
		// Without cache: a bypass cookie makes Slipstream step aside (nothing is stored).
		$slow = array();
		for ( $i = 0; $i < 2; $i++ ) {
			$r = self::timed_get( $url, array( 'slipstream_bypass' => '1' ) );
			if ( ! $r ) {
				wp_send_json_error( array( 'message' => __( 'Could not reach the home page from the server.', 'slipstream' ) ) );
			}
			$slow[] = $r['ms'];
		}
		self::timed_get( $url ); // Makes sure a cached copy exists.
		$fast = array();
		$hit  = false;
		$size = 0;
		for ( $i = 0; $i < 3; $i++ ) {
			$r = self::timed_get( $url );
			if ( $r ) {
				$fast[] = $r['ms'];
				$hit    = $hit || $r['hit'];
				$size   = $r['size'];
			}
		}
		$slow_ms = min( $slow );
		$fast_ms = $fast ? min( $fast ) : 0;
		$result  = array(
			'without' => $slow_ms,
			'with'    => $fast_ms,
			'hit'     => $hit,
			'factor'  => $fast_ms ? round( $slow_ms / max( 1, $fast_ms ), 1 ) : 0,
			'size'    => Slipstream_Util::bytes( $size ),
			'when'    => time(),
		);
		update_option( 'slipstream_last_speed', $result, false );
		wp_send_json_success( $result );
	}

	public static function ajax_status() {
		self::guard_ajax();
		list( $pages, $bytes ) = Slipstream_Cache::stats();
		$p = Slipstream_Preload::state();
		wp_send_json_success(
			array(
				'pages'   => $pages,
				'bytes'   => Slipstream_Util::bytes( $bytes ),
				'preload' => array(
					'done'    => (int) $p['done'],
					'total'   => (int) $p['total'],
					'running' => ! empty( $p['queue'] ),
				),
			)
		);
	}

	/**
	 * Findings for the Prasat Digital one-click site check (Prasat_Hub::run_check).
	 */
	public static function health() {
		global $wpdb;
		$s    = Slipstream_Settings::all();
		$page = admin_url( 'admin.php?page=slipstream' );
		$out  = array();
		$out[] = array( 'id' => 'page_cache', 'status' => $s['cache'] ? 'pass' : 'fail', 'severity' => 'medium', 'title' => __( 'Pages are cached', 'slipstream' ), 'detail' => $s['cache'] ? '' : __( 'Every visit makes WordPress build the page again. Turn the page cache on.', 'slipstream' ), 'fix_url' => $page . '&tab=cache' );
		if ( $s['cache'] ) {
			$state = Slipstream_Cache::dropin_state();
			if ( 'foreign' === $state ) {
				$out[] = array( 'id' => 'cache_conflict', 'status' => 'warn', 'severity' => 'medium', 'title' => __( 'Only one caching plugin is active', 'slipstream' ), 'detail' => __( 'Another caching plugin owns the page-cache file, so Slipstream runs in its slower standard mode. Keep one caching plugin.', 'slipstream' ), 'fix_url' => admin_url( 'plugins.php' ) );
			} elseif ( ! Slipstream_Cache::dropin_active() ) {
				$out[] = array( 'id' => 'cache_fast', 'status' => 'warn', 'severity' => 'low', 'title' => __( 'Cached pages skip WordPress entirely', 'slipstream' ), 'detail' => __( 'Slipstream couldn\'t switch on its fast mode (wp-config.php isn\'t writable). Cached pages still help, just a little less.', 'slipstream' ), 'fix_url' => $page );
			}
		}
		$revisions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" ); // phpcs:ignore
		$spam      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')" ); // phpcs:ignore
		$clutter   = $revisions + $spam;
		$out[]     = array( 'id' => 'db_clutter', 'status' => $clutter > 2000 ? 'warn' : 'pass', 'severity' => 'low', 'title' => __( 'The database is tidy', 'slipstream' ), 'detail' => $clutter > 2000 ? sprintf( __( '%s old revisions and deleted or spam comments slow the database down.', 'slipstream' ), number_format_i18n( $clutter ) ) : '', 'fix_url' => $page . '&tab=database', 'fix_label' => __( 'Clean up', 'slipstream' ) );
		return $out;
	}
}
