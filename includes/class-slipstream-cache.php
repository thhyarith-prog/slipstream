<?php
/**
 * Page cache: stores finished pages as static HTML (plus a gzip copy) and clears exactly the
 * pages affected when content changes.
 *
 * Serving happens in wp-content/advanced-cache.php before WordPress loads. If that drop-in
 * can't be installed (another cache plugin, read-only wp-config.php), Slipstream serves from
 * the same files as soon as the plugin itself loads, which is still far faster than building
 * the page.
 *
 * @package Slipstream
 */

defined( 'ABSPATH' ) || exit;

class Slipstream_Cache {

	const MARKER = 'Slipstream advanced-cache drop-in';

	/** @var bool */
	private static $buffering = false;

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'start' ), -1000 );

		// Clear what changed.
		add_action( 'transition_post_status', array( __CLASS__, 'on_status' ), 10, 3 );
		add_action( 'save_post', array( __CLASS__, 'on_save' ), 20, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'purge_post' ) );
		add_action( 'comment_post', array( __CLASS__, 'on_comment' ), 20, 2 );
		add_action( 'wp_set_comment_status', array( __CLASS__, 'on_comment_status' ), 20 );
		add_action( 'edit_comment', array( __CLASS__, 'on_comment_status' ), 20 );
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'on_product' ) );
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'on_product' ) );
		foreach ( array( 'switch_theme', 'customize_save_after', 'wp_update_nav_menu', 'activated_plugin', 'deactivated_plugin', 'upgrader_process_complete', 'update_option_sidebars_widgets', 'update_option_permalink_structure', 'update_option_blogname', 'update_option_blogdescription', 'update_option_show_on_front', 'update_option_page_on_front', 'update_option_page_for_posts', 'slipstream_settings_updated' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'purge_all_soon' ) );
		}
		add_action( 'shutdown', array( __CLASS__, 'maybe_purge_all' ), 0 );
		add_action( 'slipstream_cleanup', array( __CLASS__, 'cleanup_expired' ) );
	}

	/* ================================================================ paths */

	public static function dir() {
		return WP_CONTENT_DIR . '/cache/slipstream';
	}

	public static function config_file() {
		return WP_CONTENT_DIR . '/cache/slipstream-config.php';
	}

	/** Settings the drop-in needs, without touching the database. */
	public static function config() {
		$s = Slipstream_Settings::all();
		return array(
			'enabled'         => (bool) $s['cache'],
			'dir'             => self::dir(),
			'lifespan'        => (int) $s['lifespan_hours'] * HOUR_IN_SECONDS,
			'mobile'          => (bool) $s['mobile_cache'],
			'ignore_query'    => (array) $s['ignore_query'],
			'exclude_paths'   => (array) $s['exclude_paths'],
			'exclude_cookies' => (array) $s['exclude_cookies'],
			'exclude_agents'  => (array) $s['exclude_agents'],
			'lib'             => SLIPSTREAM_DIR . 'includes/class-slipstream-serve.php',
		);
	}

	public static function write_config() {
		wp_mkdir_p( dirname( self::config_file() ) );
		$php = "<?php\n// Written by Slipstream. Edit the settings in WordPress, not this file.\ndefined( 'ABSPATH' ) || exit;\nreturn " . var_export( self::config(), true ) . ";\n"; // phpcs:ignore
		$tmp = self::config_file() . '.' . wp_generate_password( 6, false ) . '.tmp';
		if ( false !== @file_put_contents( $tmp, $php ) ) { // phpcs:ignore
			@rename( $tmp, self::config_file() ); // phpcs:ignore -- atomic swap.
		}
		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( self::config_file(), true ); // phpcs:ignore
		}
	}

	/* ============================================================== drop-in */

	public static function dropin_file() {
		return WP_CONTENT_DIR . '/advanced-cache.php';
	}

	/** ours | missing | foreign */
	public static function dropin_state() {
		$f = self::dropin_file();
		if ( ! is_file( $f ) ) {
			return 'missing';
		}
		return false !== strpos( (string) @file_get_contents( $f, false, null, 0, 600 ), self::MARKER ) ? 'ours' : 'foreign'; // phpcs:ignore
	}

	/** True when cached pages are served before WordPress loads. */
	public static function dropin_active() {
		return 'ours' === self::dropin_state() && defined( 'WP_CACHE' ) && WP_CACHE;
	}

	public static function install() {
		self::write_config();
		Slipstream_Util::protect_dir( self::dir() );
		if ( 'foreign' === self::dropin_state() ) {
			return; // Another cache plugin owns it; we fall back to serving at plugin load.
		}
		@copy( SLIPSTREAM_DIR . 'dropin/advanced-cache.php', self::dropin_file() ); // phpcs:ignore
		self::set_wp_cache( true );
	}

	public static function uninstall() {
		if ( 'ours' === self::dropin_state() ) {
			@unlink( self::dropin_file() ); // phpcs:ignore
		}
		self::set_wp_cache( false );
		@unlink( self::config_file() ); // phpcs:ignore
		self::purge_all();
	}

	public static function wp_config_file() {
		if ( is_file( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}
		$up = dirname( ABSPATH ) . '/wp-config.php';
		return is_file( $up ) && ! is_file( dirname( ABSPATH ) . '/wp-settings.php' ) ? $up : '';
	}

	/**
	 * Add (or remove) "define( 'WP_CACHE', true );" in wp-config.php. Only our own marked line
	 * is ever removed, and the file is replaced atomically.
	 */
	public static function set_wp_cache( $on ) {
		$file = self::wp_config_file();
		if ( ! $file || ! is_writable( $file ) ) {
			return false;
		}
		$src = (string) file_get_contents( $file );
		$ours = "define( 'WP_CACHE', true ); // Added by Slipstream";
		if ( $on ) {
			if ( preg_match( "/^\s*define\(\s*['\"]WP_CACHE['\"]/m", $src ) ) {
				return defined( 'WP_CACHE' ) && WP_CACHE; // Someone else set it; leave their line alone.
			}
			$new = preg_replace( '/^<\?php\s*/', "<?php\n" . $ours . "\n", $src, 1, $n );
			if ( ! $n ) {
				return false;
			}
		} else {
			$new = str_replace( $ours . "\n", '', $src );
			if ( $new === $src ) {
				return true;
			}
		}
		$tmp = $file . '.slipstream-tmp';
		if ( false === @file_put_contents( $tmp, $new ) || ! @rename( $tmp, $file ) ) { // phpcs:ignore
			@unlink( $tmp ); // phpcs:ignore
			return false;
		}
		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $file, true ); // phpcs:ignore
		}
		return true;
	}

	/* =============================================================== store */

	/** Why this page must not be stored, or '' when it may be. */
	public static function skip_reason() {
		if ( true !== ( $r = Slipstream_Serve::cacheable_request( self::config() ) ) ) { // phpcs:ignore
			return $r;
		}
		if ( is_user_logged_in() ) {
			return 'logged-in';
		}
		// Plain permalinks: content lives behind ?p=… (never cached), and any made-up path shows
		// the home page with status 200. Only the real front page is stored, so bots requesting
		// random URLs can't fill the disk.
		if ( '' === (string) get_option( 'permalink_structure' ) && untrailingslashit( (string) Slipstream_Serve::path_part() ) !== untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ) ) {
			return 'plain-permalinks';
		}
		if ( is_404() ) {
			return '404';
		}
		if ( ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) || is_preview() || is_search() || is_feed() || is_trackback() || is_robots() || is_embed() || is_customize_preview() ) {
			return 'page-type';
		}
		if ( is_singular() && post_password_required() ) {
			return 'password';
		}
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return 'shop';
		}
		return '';
	}

	public static function start() {
		if ( self::$buffering || is_admin() ) {
			return;
		}
		$optimize = Slipstream_Optimize::wants();
		$store    = '' === self::skip_reason();
		if ( ! $optimize && ! $store ) {
			return;
		}
		self::$buffering = true;
		ob_start(
			function ( $html ) use ( $optimize, $store ) {
				return Slipstream_Cache::finish( (string) $html, $optimize, $store );
			}
		);
	}

	/** Output-buffer end: optimize the HTML, then store it if this response may be cached. */
	public static function finish( $html, $optimize, $store ) {
		if ( ! self::is_html_page( $html ) ) {
			return $html;
		}
		if ( $optimize ) {
			$html = Slipstream_Optimize::process( $html );
		}
		if ( $store && 200 === http_response_code() && ! self::sets_cookie() && ! ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) ) {
			self::write( $html );
		}
		return $html;
	}

	private static function is_html_page( $html ) {
		if ( strlen( $html ) < 255 || false === stripos( $html, '</html>' ) ) {
			return false;
		}
		foreach ( headers_list() as $h ) {
			if ( 0 === stripos( $h, 'content-type:' ) && false === stripos( $h, 'text/html' ) ) {
				return false;
			}
		}
		return true;
	}

	/** A page that sets a cookie is personal (session, cart…): never store it. */
	private static function sets_cookie() {
		foreach ( headers_list() as $h ) {
			if ( 0 === stripos( $h, 'set-cookie:' ) ) {
				return true;
			}
		}
		return false;
	}

	private static function write( $html ) {
		$file = Slipstream_Serve::file( self::config() );
		if ( '' === $file ) {
			return;
		}
		$dir = dirname( $file );
		if ( ! wp_mkdir_p( $dir ) ) {
			return;
		}
		$html .= "\n<!-- Cached by Slipstream on " . gmdate( 'Y-m-d H:i:s' ) . ' UTC -->';
		$tmp   = $file . '.' . wp_generate_password( 8, false ) . '.tmp';
		if ( false === @file_put_contents( $tmp, $html ) ) { // phpcs:ignore
			return;
		}
		@rename( $tmp, $file . '.html' ); // phpcs:ignore -- atomic: visitors never see half a file.
		if ( function_exists( 'gzencode' ) ) {
			$gz = gzencode( $html, 6 );
			if ( false !== $gz && false !== @file_put_contents( $tmp, $gz ) ) { // phpcs:ignore
				@rename( $tmp, $file . '.html.gz' ); // phpcs:ignore
			}
		}
		@unlink( $tmp ); // phpcs:ignore
		delete_transient( 'slipstream_stats' );
	}

	/* =============================================================== purge */

	/** Cache folder for a URL on this site ('' for other sites). */
	public static function url_dir( $url ) {
		$p    = wp_parse_url( $url );
		$home = wp_parse_url( home_url() );
		if ( empty( $p['host'] ) || strtolower( $p['host'] ) !== strtolower( $home['host'] ) ) {
			return '';
		}
		$host = strtolower( $p['host'] ) . ( ! empty( $p['port'] ) ? '_' . (int) $p['port'] : '' );
		$path = trim( rawurldecode( isset( $p['path'] ) ? $p['path'] : '/' ), '/' );
		if ( false !== strpos( $path, '..' ) ) {
			return '';
		}
		if ( '' !== $path && preg_match( '#[^A-Za-z0-9_\-./~@+]#', $path ) ) {
			$path = '_h/' . md5( $path );
		}
		return self::dir() . '/' . $host . ( '' === $path ? '' : '/' . $path );
	}

	/** Remove the cached copies of one URL; with $paged also its /page/N/ pages. */
	public static function purge_url( $url, $paged = false ) {
		$dir = self::url_dir( $url );
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}
		foreach ( (array) glob( $dir . '/index*' ) as $f ) {
			@unlink( $f ); // phpcs:ignore
		}
		if ( $paged ) {
			Slipstream_Util::rrmdir( $dir . '/page' );
		}
		delete_transient( 'slipstream_stats' );
		do_action( 'slipstream_purged_url', $url );
	}

	public static function purge_all() {
		Slipstream_Util::rrmdir( self::dir(), true );
		Slipstream_Util::protect_dir( self::dir() );
		delete_transient( 'slipstream_stats' );
		do_action( 'slipstream_purged_all' );
	}

	/** Many hooks fire together (a theme switch, a bulk update): clear once, at the end. */
	public static function purge_all_soon() {
		$GLOBALS['slipstream_purge_all'] = true;
	}

	public static function maybe_purge_all() {
		if ( ! empty( $GLOBALS['slipstream_purge_all'] ) ) {
			unset( $GLOBALS['slipstream_purge_all'] );
			self::purge_all();
			if ( Slipstream_Settings::get( 'preload' ) ) {
				Slipstream_Preload::queue();
			}
		}
	}

	/** Everything a post appears on: itself, home, blog page, its archives and feeds. */
	public static function purge_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || wp_is_post_revision( $post ) || 'nav_menu_item' === $post->post_type ) {
			return;
		}
		$pto = get_post_type_object( $post->post_type );
		if ( ! $pto || ! $pto->public ) {
			return;
		}
		$urls = array( get_permalink( $post ) );
		// While publishing/unpublishing the permalink can be the "?p=" form; also clear the pretty one.
		if ( ! empty( $post->post_name ) ) {
			$clone              = clone $post;
			$clone->post_status = 'publish';
			$urls[]             = get_permalink( $clone );
		}
		$paged = array( home_url( '/' ) );
		if ( get_option( 'page_for_posts' ) ) {
			$paged[] = get_permalink( get_option( 'page_for_posts' ) );
		}
		if ( $pto->has_archive ) {
			$paged[] = get_post_type_archive_link( $post->post_type );
		}
		$paged[] = get_author_posts_url( $post->post_author );
		foreach ( get_object_taxonomies( $post->post_type ) as $tax ) {
			$t = get_taxonomy( $tax );
			if ( ! $t || ! $t->public ) {
				continue;
			}
			foreach ( (array) wp_get_post_terms( $post->ID, $tax, array( 'fields' => 'ids' ) ) as $term_id ) {
				$link = get_term_link( (int) $term_id, $tax );
				if ( ! is_wp_error( $link ) ) {
					$paged[] = $link;
				}
			}
		}
		$paged[] = get_day_link( (int) get_the_date( 'Y', $post ), (int) get_the_date( 'm', $post ), (int) get_the_date( 'd', $post ) );
		$paged[] = get_month_link( (int) get_the_date( 'Y', $post ), (int) get_the_date( 'm', $post ) );
		$paged[] = get_year_link( (int) get_the_date( 'Y', $post ) );
		foreach ( array_unique( array_filter( $urls ) ) as $u ) {
			self::purge_url( $u );
		}
		foreach ( array_unique( array_filter( $paged ) ) as $u ) {
			self::purge_url( $u, true );
		}
		if ( Slipstream_Settings::get( 'preload' ) ) {
			Slipstream_Preload::queue( array_merge( array( home_url( '/' ) ), $urls ) );
		}
	}

	public static function on_status( $new, $old, $post ) {
		if ( 'publish' === $new || 'publish' === $old ) {
			self::purge_post( $post->ID );
		}
	}

	public static function on_save( $post_id, $post ) {
		if ( 'publish' === $post->post_status && ! ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			self::purge_post( $post_id );
		}
	}

	public static function on_comment( $comment_id, $approved ) {
		if ( 1 === (int) $approved ) {
			self::on_comment_status( $comment_id );
		}
	}

	public static function on_comment_status( $comment_id ) {
		$c = get_comment( $comment_id );
		if ( $c && $c->comment_post_ID ) {
			self::purge_url( get_permalink( $c->comment_post_ID ) );
		}
	}

	public static function on_product( $product ) {
		$id = is_object( $product ) && method_exists( $product, 'get_id' ) ? $product->get_id() : (int) $product;
		if ( is_object( $product ) && method_exists( $product, 'get_parent_id' ) && $product->get_parent_id() ) {
			$id = $product->get_parent_id();
		}
		self::purge_post( $id );
	}

	/** Hourly: drop pages older than the lifespan (they would be rebuilt anyway). */
	public static function cleanup_expired() {
		$life = (int) Slipstream_Settings::get( 'lifespan_hours' ) * HOUR_IN_SECONDS;
		if ( ! $life || ! is_dir( self::dir() ) ) {
			return;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( self::dir(), FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( $f->isFile() && 0 === strpos( $f->getFilename(), 'index' ) && $f->getMTime() < time() - $life ) {
				@unlink( $f->getPathname() ); // phpcs:ignore
			}
		}
		delete_transient( 'slipstream_stats' );
	}

	/** [pages, bytes] — cached for a minute. */
	public static function stats() {
		$s = get_transient( 'slipstream_stats' );
		if ( is_array( $s ) ) {
			return $s;
		}
		$pages = 0;
		$bytes = 0;
		if ( is_dir( self::dir() ) ) {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( self::dir(), FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $f ) {
				if ( ! $f->isFile() ) {
					continue;
				}
				if ( '.html' === substr( $f->getFilename(), -5 ) ) {
					$pages++;
				}
				$bytes += $f->getSize();
			}
		}
		$s = array( $pages, $bytes );
		set_transient( 'slipstream_stats', $s, MINUTE_IN_SECONDS );
		return $s;
	}
}
