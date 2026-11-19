<?php
/**
 * Prasat Digital hub: one "Prasat Digital" admin menu for every Prasat Digital plugin, with an
 * overview page. Every plugin ships this same file; the first one loaded defines the class and
 * the others register with it, so the menu appears once however many are installed.
 *
 * Keep this file identical in every Prasat Digital plugin.
 *
 * @package Prasat_Hub
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Prasat_Hub', false ) ) {

	final class Prasat_Hub {

		const SLUG    = 'prasat-digital';
		const VERSION = '1.0.3';
		const CAP     = 'manage_options';
		const SITE    = 'https://www.prasatdigital.com';
		const FOUNDER = 'Thyyarith';
		const MOTTO   = 'Every website deserves to stand strong, load fast and stay safe.';

		/** @var array id => [ 'assets' => URL of that plugin's assets/ folder ] */
		private static $registered = array();

		/** A plugin announces itself; the first call wires up the menu. */
		public static function register( $id, array $args ) {
			self::$registered[ $id ] = $args;
			if ( ! has_action( 'admin_menu', array( __CLASS__, 'menu' ) ) ) {
				add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
				add_action( 'admin_menu', array( __CLASS__, 'tidy' ), 999 );
				add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
				add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
				add_action( 'admin_head', array( __CLASS__, 'menu_icon_style' ) );
				add_filter( 'admin_footer_text', array( __CLASS__, 'footer_credit' ), 20 );
				add_action( 'wp_ajax_prasat_site_check', array( __CLASS__, 'ajax_check' ) );
			}
		}

		/* ======================================================== site check */

		const CHECK_OPTION = 'prasat_site_check';
		const WEIGHTS      = array( 'critical' => 20, 'high' => 10, 'medium' => 5, 'low' => 2 );

		/**
		 * One finding in the common format every plugin's health() returns:
		 * { id, status pass|warn|fail|info, severity critical|high|medium|low, title, detail, fix_url, fix_label }.
		 */
		public static function item( $id, $status, $severity, $title, $detail = '', $fix_url = '', $fix_label = '' ) {
			return compact( 'id', 'status', 'severity', 'title', 'detail', 'fix_url', 'fix_label' );
		}

		private static function normalize( $raw, $plugin ) {
			if ( ! is_array( $raw ) || empty( $raw['id'] ) || empty( $raw['title'] ) ) {
				return null;
			}
			$status   = isset( $raw['status'] ) && in_array( $raw['status'], array( 'pass', 'warn', 'fail', 'info' ), true ) ? $raw['status'] : 'warn';
			$severity = isset( $raw['severity'] ) && isset( self::WEIGHTS[ $raw['severity'] ] ) ? $raw['severity'] : 'low';
			$url      = isset( $raw['fix_url'] ) ? esc_url_raw( (string) $raw['fix_url'] ) : '';
			if ( $url && 0 !== strpos( $url, admin_url() ) && 0 !== strpos( $url, 'https://' ) ) {
				$url = '';
			}
			return array(
				'plugin'    => $plugin,
				'id'        => sanitize_key( $raw['id'] ),
				'status'    => $status,
				'severity'  => $severity,
				'title'     => wp_strip_all_tags( (string) $raw['title'] ),
				'detail'    => isset( $raw['detail'] ) ? wp_strip_all_tags( (string) $raw['detail'] ) : '',
				'fix_url'   => $url,
				'fix_label' => ! empty( $raw['fix_label'] ) ? wp_strip_all_tags( (string) $raw['fix_label'] ) : __( 'Fix', 'default' ),
			);
		}

		/** WordPress-level checks the hub runs itself; a plugin's item with the same id wins. */
		private static function core_checks() {
			require_once ABSPATH . 'wp-admin/includes/update.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$out  = array();
			$core = get_core_updates();
			$cu   = is_array( $core ) && isset( $core[0]->response ) && 'upgrade' === $core[0]->response;
			$out[] = self::item( 'core_update', $cu ? 'fail' : 'pass', 'high', 'WordPress is up to date', $cu ? sprintf( 'Version %s is available.', $core[0]->current ) : 'You run the latest WordPress.', admin_url( 'update-core.php' ), 'Update' );
			$pu   = get_site_transient( 'update_plugins' );
			$np   = isset( $pu->response ) ? count( (array) $pu->response ) : 0;
			$out[] = self::item( 'plugin_updates', $np ? 'fail' : 'pass', 'high', 'Plugins are up to date', $np ? sprintf( '%d plugins have updates waiting. Outdated plugins are the most common way sites get hacked.', $np ) : 'All plugins are current.', admin_url( 'update-core.php' ), 'Update' );
			$tu   = get_site_transient( 'update_themes' );
			$nt   = isset( $tu->response ) ? count( (array) $tu->response ) : 0;
			$out[] = self::item( 'theme_updates', $nt ? 'warn' : 'pass', 'low', 'Themes are up to date', $nt ? sprintf( '%d themes have updates waiting.', $nt ) : 'All themes are current.', admin_url( 'update-core.php' ), 'Update' );
			$php  = version_compare( PHP_VERSION, '8.1', '>=' ) ? 'pass' : ( version_compare( PHP_VERSION, '7.4', '>=' ) ? 'warn' : 'fail' );
			$out[] = self::item( 'php', $php, 'medium', 'Supported PHP version', sprintf( 'Your server runs PHP %s. Ask your host for PHP 8.2 or newer: it is faster and still gets security fixes.', PHP_VERSION ) );
			$https = 0 === strpos( home_url(), 'https://' );
			$out[] = self::item( 'https', $https ? 'pass' : 'fail', 'high', 'Site uses HTTPS', $https ? 'Visitors connect securely.' : 'Without HTTPS, browsers mark the site "Not secure" and passwords travel unencrypted.', admin_url( 'options-general.php' ) );
			$debug = defined( 'WP_DEBUG' ) && WP_DEBUG && ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY );
			$out[] = self::item( 'debug_display', $debug ? 'fail' : 'pass', 'medium', 'Errors are hidden from visitors', $debug ? 'WP_DEBUG shows PHP errors on the pages, which reveals file paths to attackers. Turn it off in wp-config.php.' : 'PHP errors are not shown on the site.' );
			$admin = get_user_by( 'login', 'admin' );
			$out[] = self::item( 'admin_username', $admin ? 'warn' : 'pass', 'medium', 'No user called "admin"', $admin ? '"admin" is the first username bots try. Create a new administrator with another name and delete this one.' : 'Good: bots have to guess the username too.', admin_url( 'users.php' ) );
			$public = (bool) get_option( 'blog_public' );
			$out[] = self::item( 'blog_public', $public ? 'pass' : 'fail', 'critical', 'Search engines may show the site', $public ? 'Google and others can list your pages.' : '"Discourage search engines" is ticked, so the site is hidden from Google.', admin_url( 'options-reading.php' ) );
			$inactive = 0;
			foreach ( array_keys( get_plugins() ) as $file ) {
				$inactive += is_plugin_active( $file ) ? 0 : 1;
			}
			$out[] = self::item( 'inactive_plugins', $inactive ? 'warn' : 'pass', 'low', 'No unused plugins left installed', $inactive ? sprintf( '%d plugins are installed but switched off. Delete the ones you don\'t need: their code can still be attacked.', $inactive ) : 'Every installed plugin is in use.', admin_url( 'plugins.php?plugin_status=inactive' ), 'Review' );
			$waiting = (int) wp_count_comments()->moderated;
			$out[] = self::item( 'comment_backlog', $waiting > 500 ? 'fail' : ( $waiting > 20 ? 'warn' : 'pass' ), $waiting > 500 ? 'high' : 'medium', 'Comments are kept under control', $waiting > 20 ? sprintf( '%s comments are waiting for approval, most likely spam.', number_format_i18n( $waiting ) ) : 'No pile of waiting comments.', admin_url( 'edit-comments.php?comment_status=moderated' ), 'Review' );
			$backup = false;
			foreach ( array( 'updraftplus/updraftplus.php', 'backwpup/backwpup.php', 'duplicator/duplicator.php', 'all-in-one-wp-migration/all-in-one-wp-migration.php', 'jetpack/jetpack.php', 'lifeboat-backup/lifeboat-backup.php' ) as $b ) {
				$backup = $backup || is_plugin_active( $b );
			}
			$out[] = self::item( 'backup_plugin', $backup ? 'pass' : 'fail', 'critical', 'The site is backed up', $backup ? 'A backup plugin is active.' : 'No backup plugin is active. If the site breaks or gets hacked there is nothing to restore.', self::SITE, 'Get Lifeboat' );
			return $out;
		}

		/** Ask every installed Prasat plugin for its findings, add WordPress checks, score it, keep it. */
		public static function run_check() {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 60 ); // phpcs:ignore
			}
			$pu = get_site_transient( 'update_plugins' );
			if ( ! $pu || ! isset( $pu->last_checked ) || $pu->last_checked < time() - HOUR_IN_SECONDS ) {
				wp_version_check();
				wp_update_plugins();
				wp_update_themes();
			}
			$items = array();
			$seen  = array();
			foreach ( array_keys( self::catalog() ) as $id ) {
				if ( empty( self::$registered[ $id ]['health'] ) || ! is_callable( self::$registered[ $id ]['health'] ) ) {
					continue;
				}
				try {
					$found = (array) call_user_func( self::$registered[ $id ]['health'] );
				} catch ( Throwable $e ) {
					$found = array( self::item( 'check_failed', 'warn', 'low', sprintf( 'Couldn\'t check %s', self::catalog()[ $id ]['name'] ), $e->getMessage() ) );
				}
				foreach ( $found as $raw ) {
					$it = self::normalize( $raw, $id );
					if ( $it && ! isset( $seen[ $it['id'] ] ) ) {
						$seen[ $it['id'] ] = true;
						$items[]           = $it;
					}
				}
			}
			foreach ( self::core_checks() as $raw ) {
				$it = self::normalize( $raw, 'wordpress' );
				if ( $it && ! isset( $seen[ $it['id'] ] ) ) {
					$seen[ $it['id'] ] = true;
					$items[]           = $it;
				}
			}
			$items = apply_filters( 'prasat_site_check_items', $items );
			$score = self::score( $items );
			$order = array( 'fail' => 0, 'warn' => 1, 'info' => 2, 'pass' => 3 );
			$sev   = array( 'critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3 );
			usort(
				$items,
				function ( $a, $b ) use ( $order, $sev ) {
					return $order[ $a['status'] ] <=> $order[ $b['status'] ] ?: $sev[ $a['severity'] ] <=> $sev[ $b['severity'] ];
				}
			);
			$result = array(
				'time'    => time(),
				'score'   => $score,
				'grade'   => $score >= 90 ? 'A' : ( $score >= 75 ? 'B' : ( $score >= 60 ? 'C' : ( $score >= 40 ? 'D' : 'F' ) ) ),
				'items'   => $items,
				'missing' => self::missing_products(),
			);
			update_option( self::CHECK_OPTION, $result, false );
			do_action( 'prasat_site_check_done', $result );
			return $result;
		}

		/**
		 * 0–100: each finding weighs by severity; a warning earns half. A failing critical item caps
		 * the score at 49, a failing high item at 79, so a serious problem is never hidden by many
		 * small passes.
		 */
		public static function score( array $items ) {
			$total = 0;
			$got   = 0;
			$cap   = 100;
			foreach ( $items as $it ) {
				if ( 'info' === $it['status'] ) {
					continue;
				}
				$w      = self::WEIGHTS[ $it['severity'] ];
				$total += $w;
				$got   += 'pass' === $it['status'] ? $w : ( 'warn' === $it['status'] ? $w / 2 : 0 );
				if ( 'fail' === $it['status'] ) {
					$cap = min( $cap, 'critical' === $it['severity'] ? 49 : ( 'high' === $it['severity'] ? 79 : 100 ) );
				}
			}
			return $total ? (int) min( $cap, round( 100 * $got / $total ) ) : 100;
		}

		/** Prasat plugins that would cover areas this site doesn't have yet. */
		private static function missing_products() {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$out = array();
			foreach ( self::catalog() as $id => $p ) {
				if ( ! is_plugin_active( $p['file'] ) ) {
					$out[] = array( 'id' => $id, 'name' => $p['name'], 'desc' => $p['desc'] );
				}
			}
			return $out;
		}

		public static function ajax_check() {
			check_ajax_referer( 'prasat_site_check', 'nonce' );
			if ( ! current_user_can( self::CAP ) ) {
				wp_send_json_error( null, 403 );
			}
			wp_send_json_success( self::run_check() );
		}

		/**
		 * What a premium AI summary may see: scores and findings, never URLs, names, emails or IPs.
		 * The premium add-on (Prasat relay) hooks prasat_site_check_ai; nothing here calls it.
		 */
		public static function ai_payload( array $result ) {
			global $wp_version;
			return array(
				'score'   => $result['score'],
				'grade'   => $result['grade'],
				'wp'      => $wp_version,
				'php'     => PHP_VERSION,
				'items'   => array_map(
					function ( $i ) {
						return array_intersect_key( $i, array_flip( array( 'plugin', 'id', 'status', 'severity', 'title' ) ) );
					},
					$result['items']
				),
			);
		}

		/** URL of a family icon (prasat, bastion, lifeboat, slipstream, compass, featherweight). */
		public static function icon_url( $id ) {
			$assets = self::assets_url();
			return $assets ? $assets . 'img/icon-' . sanitize_key( $id ) . '.png' : '';
		}

		/** True on the hub and on every Prasat Digital plugin screen. */
		private static function on_family_page() {
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
			if ( '' === $page ) {
				return false;
			}
			if ( self::SLUG === $page ) {
				return true;
			}
			foreach ( self::catalog() as $p ) {
				if ( 0 === strpos( $page, $p['page'] ) ) {
					return true;
				}
			}
			return false;
		}

		/** Replaces "Thank you for creating with WordPress" on our screens. */
		public static function footer_credit( $text ) {
			if ( ! self::on_family_page() ) {
				return $text;
			}
			// The overview page already shows the motto in its own card.
			$motto = self::on_page() ? '' : '<em>&ldquo;' . esc_html( self::MOTTO ) . '&rdquo;</em> &middot; ';
			return '<span class="pd-foot-credit">' . $motto . 'Made by <a href="' . esc_url( self::SITE ) . '" target="_blank" rel="noopener">Prasat Digital</a> &middot; Founded by <strong>' . esc_html( self::FOUNDER ) . '</strong></span>';
		}

		/** The colour app icon in the admin menu: full size and full opacity, like other app icons. */
		public static function menu_icon_style() {
			if ( ! self::icon_url( 'prasat' ) ) {
				return;
			}
			echo '<style id="prasat-menu-icon">#adminmenu .toplevel_page_' . esc_attr( self::SLUG ) . ' .wp-menu-image img{width:20px;height:20px;padding:7px 0 0;opacity:1;border-radius:5px}</style>' . "\n";
		}

		/** Every product in the family, in menu order. */
		public static function catalog() {
			return array(
				'bastion'       => array(
					'name' => 'Bastion Security',
					'tag'  => 'Firewall · Login · Scan',
					'desc' => 'Firewall that runs before WordPress, brute-force protection, two-factor login, malware scanning and an activity log.',
					'file' => 'bastion-security/bastion-security.php',
					'page' => 'bastion',
					'icon' => '<path d="M12 2.5 4.5 5.5v5.7c0 4.7 3.2 8.9 7.5 10.3 4.3-1.4 7.5-5.6 7.5-10.3V5.5L12 2.5z"/><path d="m8.8 12 2.2 2.2 4.3-4.4"/>',
				),
				'lifeboat'      => array(
					'name' => 'Lifeboat Backup',
					'tag'  => 'Backup · Restore · Protect',
					'desc' => 'Automatic, encrypted backups to Google Drive, OneDrive, Dropbox, Box and MEGA, with one-click restore and rollback of bad updates.',
					'file' => 'lifeboat-backup/lifeboat-backup.php',
					'page' => 'lifeboat',
					'icon' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="M5.64 5.64l3.53 3.53M14.83 14.83l3.53 3.53M18.36 5.64l-3.53 3.53M9.17 14.83l-3.53 3.53"/>',
				),
				'slipstream'    => array(
					'name' => 'Slipstream',
					'tag'  => 'Cache · Optimize · Speed',
					'desc' => 'Page cache with preloading, deferred or delayed JavaScript, lazy media, YouTube click-to-load, CDN and database clean-up.',
					'file' => 'slipstream/slipstream.php',
					'page' => 'slipstream',
					'icon' => '<path d="M3 7h11a3 3 0 1 0-3-3"/><path d="M3 12h16a3 3 0 1 1-3 3"/><path d="M3 17h7"/>',
				),
				'compass'       => array(
					'name' => 'Compass',
					'tag'  => 'Search · Social · Schema',
					'desc' => 'SEO titles and descriptions with a live Google preview, keyphrase checks, business schema, sitemap, redirects and a 404 monitor.',
					'file' => 'compass/compass.php',
					'page' => 'compass',
					'icon' => '<circle cx="12" cy="12" r="9.5"/><path d="m15.8 8.2-2.1 5.5-5.5 2.1 2.1-5.5z"/>',
				),
				'featherweight' => array(
					'name' => 'Featherweight',
					'tag'  => 'WebP · WebM · Lighter pages',
					'desc' => 'Converts every image to WebP and every video to WebM and serves them to visitors. Originals are never deleted.',
					'file' => 'featherweight/featherweight.php',
					'page' => 'featherweight',
					'icon' => '<path d="M20.2 3.8a6 6 0 0 0-8.5 0L5 10.5V19h8.5l6.7-6.7a6 6 0 0 0 0-8.5z"/><path d="M16 8 2 22"/><path d="M17.5 15H9"/>',
				),
			);
		}

		public static function icon( $id, $size = 26 ) {
			$c = self::catalog();
			return isset( $c[ $id ] ) ? '<svg viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $c[ $id ]['icon'] . '</svg>' : '';
		}

		/** The Prasat mark (stepped temple) for the admin menu; WordPress recolours it per admin theme. */
		private static function menu_icon() {
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="8 7 48 40"><g fill="black"><rect x="25" y="9" width="14" height="6"/><rect x="20" y="18" width="24" height="6"/><rect x="15" y="27" width="34" height="6"/><rect x="10" y="36" width="44" height="6"/></g></svg>';
			return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore
		}

		public static function menu() {
			$icon = self::icon_url( 'prasat' );
			add_menu_page( 'Prasat Digital', 'Prasat Digital', self::CAP, self::SLUG, array( __CLASS__, 'render' ), $icon ? $icon : self::menu_icon(), 80 );
		}

		/** "Overview" first, then the products in family order. */
		public static function tidy() {
			global $submenu;
			if ( empty( $submenu[ self::SLUG ] ) ) {
				return;
			}
			$order = array_merge( array( self::SLUG ), array_values( wp_list_pluck( self::catalog(), 'page' ) ) );
			$items = $submenu[ self::SLUG ];
			usort(
				$items,
				function ( $a, $b ) use ( $order ) {
					$x = array_search( $a[2], $order, true );
					$y = array_search( $b[2], $order, true );
					return ( false === $x ? 99 : $x ) - ( false === $y ? 99 : $y );
				}
			);
			foreach ( $items as &$it ) {
				if ( self::SLUG === $it[2] ) {
					$it[0] = __( 'Overview', 'default' );
				}
			}
			unset( $it );
			$submenu[ self::SLUG ] = $items; // phpcs:ignore -- reordering our own menu.
		}

		/** URL of a registered plugin's assets/ folder that has the UI kit. */
		private static function assets_url() {
			foreach ( self::$registered as $r ) {
				if ( ! empty( $r['assets'] ) ) {
					return trailingslashit( $r['assets'] );
				}
			}
			return '';
		}

		private static function on_page() {
			return isset( $_GET['page'] ) && self::SLUG === $_GET['page']; // phpcs:ignore
		}

		public static function body_class( $c ) {
			return self::on_page() ? $c . ' pd-body' : $c;
		}

		public static function assets() {
			if ( self::on_page() && self::assets_url() ) {
				wp_enqueue_style( 'prasat-hub-ui', self::assets_url() . 'prasat-ui.css', array(), self::VERSION );
				wp_enqueue_script( 'prasat-hub-ui', self::assets_url() . 'prasat-ui.js', array(), self::VERSION, true );
				$ai = apply_filters( 'prasat_site_check_ai', array( 'available' => false ) );
				wp_add_inline_script(
					'prasat-hub-ui',
					'window.PrasatCheck=' . wp_json_encode(
						array(
							'ajax'  => admin_url( 'admin-ajax.php' ),
							'nonce' => wp_create_nonce( 'prasat_site_check' ),
							'last'  => get_option( self::CHECK_OPTION ) ?: null,
							'ai'    => ! empty( $ai['available'] ),
							'names' => array_merge( wp_list_pluck( self::catalog(), 'name' ), array( 'wordpress' => 'WordPress' ) ),
						)
					) . ';',
					'before'
				);
				wp_add_inline_script( 'prasat-hub-ui', self::check_js() );
				wp_add_inline_style( 'prasat-hub-ui', '.pd-products{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-bottom:16px}.pd-product{display:flex;flex-direction:column;gap:10px;margin:0}.pd-product__head{display:flex;gap:12px;align-items:center}.pd-product__icon{flex:none;width:44px;height:44px;border-radius:10px;background:var(--pd-grad);color:#fff;display:grid;place-items:center}.pd-product__icon.is-off{background:var(--pd-soft);color:var(--pd-muted)}.pd-product__icon--img,.pd-product__icon--img.is-off{background:none;overflow:hidden;box-shadow:0 2px 6px rgba(12,29,63,.18)}.pd-product__icon img{width:44px;height:44px;display:block}.pd-product__icon.is-off img{filter:grayscale(1);opacity:.55}.pd-story{text-align:center;padding-top:22px;padding-bottom:22px}.pd-story__motto{font-size:17px;font-style:italic;color:var(--pd-ink);margin:0 0 6px}.pd-story__by{margin:0;color:var(--pd-muted)}.pd-story__by b{color:var(--pd-ink)}.pd-app .pd-product h2{margin:0}.pd-product__tag{font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--pd-muted)}.pd-product p{margin:0;color:var(--pd-text);flex:1}.pd-product .pd-actions{justify-content:space-between}.pd-support{display:flex;flex-wrap:wrap;gap:8px}' . self::check_css() );
			}
		}

		private static function check_css() {
			return '.pd-check{margin-bottom:16px}.pd-check__hero{display:grid;grid-template-columns:auto minmax(0,1fr) minmax(200px,260px);gap:24px;align-items:center}'
				. '.pd-check__ring{--p:0;--c:var(--pd-muted);width:112px;height:112px;border-radius:50%;background:conic-gradient(var(--c) calc(var(--p)*1%),var(--pd-soft) 0);display:grid;place-items:center;align-content:center;position:relative}'
				. '.pd-check__ring::before{content:"";position:absolute;inset:10px;border-radius:50%;background:#fff}.pd-check__ring>span{position:relative}'
				. '.pd-check__grade{font-size:34px;font-weight:700;line-height:1;color:var(--pd-ink)}.pd-check__num{font-size:12px;color:var(--pd-muted)}'
				. '.pd-check--good .pd-check__ring{--c:var(--pd-ok)}.pd-check--warn .pd-check__ring{--c:var(--pd-warn)}.pd-check--bad .pd-check__ring{--c:var(--pd-bad)}'
				. '.pd-app .pd-check h2{margin:0 0 4px}.pd-check__lead{margin:0 0 12px}.pd-check__status{color:var(--pd-muted)}'
				. '.pd-check__ai{border:1px dashed var(--pd-line-strong);border-radius:10px;padding:14px;display:grid;gap:6px;align-content:start}.pd-check__ai p{margin:0;font-size:12px;color:var(--pd-muted)}.pd-check__ai .pd-badge{justify-self:start}'
				. '.pd-check__results{margin-top:20px;border-top:1px solid var(--pd-line);padding-top:16px}.pd-check__group{margin:0 0 14px}.pd-check__group h3{margin:0 0 6px;font-size:11px;letter-spacing:.07em;text-transform:uppercase;color:var(--pd-muted)}'
				. '.pd-check__list{margin:0;list-style:none}.pd-check__row{display:grid;grid-template-columns:20px minmax(0,1fr) auto;gap:10px;align-items:start;padding:10px 0;border-bottom:1px solid var(--pd-line);margin:0}.pd-check__row:last-child{border-bottom:0}'
				. '.pd-check__row .pd-tick{margin-top:2px}.pd-check__row .pd-tick--bad{background:var(--pd-bad)}.pd-check__row .pd-tick--warn{background:var(--pd-warn)}.pd-check__row .pd-tick--info{background:var(--pd-indigo)}'
				. '.pd-check__title{font-weight:600;color:var(--pd-ink)}.pd-check__detail{display:block;color:var(--pd-text);font-size:13px}.pd-check__meta{display:flex;gap:6px;margin-top:4px;flex-wrap:wrap}'
				. '.pd-check__passed summary{cursor:pointer;color:var(--pd-muted);font-weight:600;padding:6px 0}.pd-check__good{color:var(--pd-ok);font-weight:600}'
				. '@media (max-width:960px){.pd-check__hero{grid-template-columns:auto minmax(0,1fr)}.pd-check__ai{grid-column:1/-1}}@media (max-width:600px){.pd-check__hero{grid-template-columns:minmax(0,1fr);justify-items:center;text-align:center}.pd-check__row{grid-template-columns:20px minmax(0,1fr)}.pd-check__row .button{grid-column:2;justify-self:start}}';
		}

		/** The card's behaviour; builds every node with textContent, so findings can't inject markup. */
		private static function check_js() {
			return <<<'JS'
(function(){var C=window.PrasatCheck,card=document.getElementById('pd-site-check');if(!C||!card)return;
var run=card.querySelector('.pd-check__run'),status=card.querySelector('.pd-check__status'),out=card.querySelector('.pd-check__results'),ring=card.querySelector('.pd-check__ring');
var SEV={critical:'Critical',high:'High',medium:'Medium',low:'Low'},AREA={wordpress:'WordPress',bastion:'Security',lifeboat:'Backups',slipstream:'Speed',compass:'Search (SEO)',featherweight:'Images'};
function el(t,c,txt){var e=document.createElement(t);if(c)e.className=c;if(txt!=null)e.textContent=txt;return e;}
function ago(ts){var s=Math.round(Date.now()/1000-ts);if(s<90)return 'just now';if(s<5400)return Math.round(s/60)+' minutes ago';if(s<129600)return Math.round(s/3600)+' hours ago';return Math.round(s/86400)+' days ago';}
function row(it){var li=el('li','pd-check__row'),tick=el('span','pd-tick'+(it.status==='fail'?' pd-tick--bad':it.status==='warn'?' pd-tick--warn':it.status==='info'?' pd-tick--info':''),it.status==='pass'?'✓':(it.status==='info'?'i':'!'));tick.setAttribute('aria-hidden','true');
var body=el('div');body.appendChild(el('span','pd-check__title',it.title));if(it.detail)body.appendChild(el('span','pd-check__detail',it.detail));
if(it.status!=='pass'){var meta=el('span','pd-check__meta');meta.appendChild(el('span','pd-badge'+(it.severity==='critical'||it.severity==='high'?' pd-badge--bad':' pd-badge--warn'),SEV[it.severity]||''));meta.appendChild(el('span','pd-badge',(C.names&&C.names[it.plugin])||it.plugin));body.appendChild(meta);}
li.appendChild(tick);li.appendChild(body);if(it.fix_url&&it.status!=='pass'){var a=el('a','button',it.fix_label||'Fix');a.href=it.fix_url;if(a.host!==location.host){a.target='_blank';a.rel='noopener';}li.appendChild(a);}return li;}
function show(r){if(!r||!r.items)return;var tone=r.score>=75?'good':r.score>=50?'warn':'bad';card.classList.remove('pd-check--good','pd-check--warn','pd-check--bad');card.classList.add('pd-check--'+tone);
ring.style.setProperty('--p',r.score);ring.querySelector('.pd-check__grade').textContent=r.grade;ring.querySelector('.pd-check__num').textContent=r.score+'/100';ring.setAttribute('aria-label','Website score '+r.score+' out of 100, grade '+r.grade);
run.textContent='Check again';var todo=r.items.filter(function(i){return i.status==='fail'||i.status==='warn';}),passed=r.items.filter(function(i){return i.status==='pass';});
status.textContent='Checked '+ago(r.time)+' · '+(todo.length?todo.length+' things to do':'nothing to do');out.textContent='';out.hidden=false;
if(!todo.length)out.appendChild(el('p','pd-check__good','Everything checked is in good shape.'));
var groups={},keys=[];todo.forEach(function(i){if(!groups[i.plugin]){groups[i.plugin]=[];keys.push(i.plugin);}groups[i.plugin].push(i);});
keys.forEach(function(k){var g=el('div','pd-check__group');g.appendChild(el('h3',null,AREA[k]||k));var ul=el('ul','pd-check__list');groups[k].forEach(function(i){ul.appendChild(row(i));});g.appendChild(ul);out.appendChild(g);});
if(r.missing&&r.missing.length){var g2=el('div','pd-check__group');g2.appendChild(el('h3',null,'Not covered yet'));var ul2=el('ul','pd-check__list');r.missing.forEach(function(m){ul2.appendChild(row({status:'info',severity:'low',plugin:m.id,title:m.name,detail:m.desc,fix_url:'https://www.prasatdigital.com',fix_label:'Learn more'}));});g2.appendChild(ul2);out.appendChild(g2);}
if(passed.length){var d=el('details','pd-check__passed');d.appendChild(el('summary',null,passed.length+' checks passed'));var ul3=el('ul','pd-check__list');passed.forEach(function(i){ul3.appendChild(row(i));});d.appendChild(ul3);out.appendChild(d);}}
run.addEventListener('click',function(){run.disabled=true;run.setAttribute('aria-busy','true');status.textContent='Checking your website…';
var b=new FormData();b.append('action','prasat_site_check');b.append('nonce',C.nonce);
fetch(C.ajax,{method:'POST',credentials:'same-origin',body:b}).then(function(r){return r.json();}).then(function(j){if(!j||!j.success)throw 0;C.last=j.data;show(j.data);}).catch(function(){status.textContent='The check could not finish. Please reload and try again.';}).then(function(){run.disabled=false;run.removeAttribute('aria-busy');});});
if(C.last)show(C.last);if(C.ai){var ai=card.querySelector('.pd-check__ai-btn');ai.disabled=false;ai.removeAttribute('aria-disabled');ai.textContent='Write AI summary';}
})();
JS;
		}

		public static function render() {
			if ( ! current_user_can( self::CAP ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'default' ) );
			}
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$installed = get_plugins();
			$assets    = self::assets_url();
			$active_n  = 0;
			foreach ( self::catalog() as $p ) {
				$active_n += is_plugin_active( $p['file'] ) ? 1 : 0;
			}
			?>
			<div class="wrap pd-app prasat-hub">
				<header class="pd-brand">
					<div class="pd-brand__id">
						<span class="pd-brand__mark<?php echo $assets ? ' pd-brand__mark--icon' : ''; ?>" aria-hidden="true">
							<?php if ( $assets ) : ?><img src="<?php echo esc_url( self::icon_url( 'prasat' ) ); ?>" alt="" width="48" height="48"><?php endif; ?>
						</span>
						<div><h1>Prasat Digital</h1><p class="pd-brand__tag">WordPress plugins</p></div>
					</div>
					<div class="pd-brand__side">
						<a class="pd-brand__status" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><span class="pd-dot" aria-hidden="true"></span><?php echo esc_html( sprintf( '%d of %d active', $active_n, count( self::catalog() ) ) ); ?></a>
						<a class="pd-credit" href="<?php echo esc_url( self::SITE ); ?>" target="_blank" rel="noopener noreferrer"><?php if ( $assets ) : ?><img src="<?php echo esc_url( $assets . 'img/prasat-mark-white.svg' ); ?>" alt="" width="20" height="20"><?php endif; ?><span><b>prasatdigital.com</b><span class="screen-reader-text"> (opens in a new tab)</span></span></a>
					</div>
				</header>
				<hr class="wp-header-end">

				<section class="pd-card pd-check" id="pd-site-check" aria-labelledby="pd-check-t">
					<div class="pd-check__hero">
						<div class="pd-check__ring" role="img" aria-label="" style="--p:0"><span class="pd-check__grade">?</span><span class="pd-check__num"></span></div>
						<div class="pd-check__intro">
							<h2 id="pd-check-t">Check my website</h2>
							<p class="pd-lead pd-check__lead">One click checks security, backups, speed, search visibility, images and WordPress itself, and lists what to do first.</p>
							<div class="pd-actions">
								<button type="button" class="button button-primary button-hero pd-check__run">Check my website</button>
								<span class="pd-check__status" role="status" aria-live="polite"></span>
							</div>
						</div>
						<aside class="pd-check__ai" aria-label="AI summary">
							<span class="pd-badge pd-badge--navy">Premium</span>
							<strong>AI summary</strong>
							<p>A plain-language report of what matters most on your site, written by AI.</p>
							<button type="button" class="button pd-check__ai-btn" aria-disabled="true" disabled>Coming soon</button>
						</aside>
					</div>
					<div class="pd-check__results" hidden></div>
				</section>

				<div class="pd-products">
					<?php foreach ( self::catalog() as $id => $p ) : ?>
						<?php
						$is_installed = isset( $installed[ $p['file'] ] );
						$is_active    = is_plugin_active( $p['file'] );
						?>
						<section class="pd-card pd-product">
							<div class="pd-product__head">
								<?php if ( $assets ) : ?>
									<span class="pd-product__icon pd-product__icon--img<?php echo $is_active ? '' : ' is-off'; ?>"><img src="<?php echo esc_url( self::icon_url( $id ) ); ?>" alt="" width="44" height="44"></span>
								<?php else : ?>
									<span class="pd-product__icon<?php echo $is_active ? '' : ' is-off'; ?>"><?php echo self::icon( $id ); // phpcs:ignore -- fixed SVG. ?></span>
								<?php endif; ?>
								<div><h2><?php echo esc_html( $p['name'] ); ?></h2><span class="pd-product__tag"><?php echo esc_html( $p['tag'] ); ?></span></div>
							</div>
							<p><?php echo esc_html( $p['desc'] ); ?></p>
							<div class="pd-actions">
								<?php if ( $is_active ) : ?>
									<span class="pd-badge pd-badge--ok">&#10003; <?php echo esc_html__( 'Active', 'default' ); ?></span>
									<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $p['page'] ) ); ?>"><?php echo esc_html__( 'Open', 'default' ); ?></a>
								<?php elseif ( $is_installed ) : ?>
									<span class="pd-badge"><?php echo esc_html__( 'Installed', 'default' ); ?></span>
									<?php if ( current_user_can( 'activate_plugins' ) ) : ?>
										<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $p['file'] ) ), 'activate-plugin_' . $p['file'] ) ); ?>"><?php echo esc_html__( 'Activate', 'default' ); ?></a>
									<?php endif; ?>
								<?php else : ?>
									<span class="pd-badge"><?php echo esc_html__( 'Not installed', 'default' ); ?></span>
									<a class="button" href="<?php echo esc_url( self::SITE ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Learn more', 'default' ); ?></a>
								<?php endif; ?>
							</div>
						</section>
					<?php endforeach; ?>
				</div>

				<section class="pd-card">
					<h2>Help &amp; support</h2>
					<p class="pd-lead">Questions, training or a custom feature? Talk to the Prasat Digital team.</p>
					<div class="pd-support">
						<a class="button" href="<?php echo esc_url( self::SITE ); ?>" target="_blank" rel="noopener">prasatdigital.com</a>
						<a class="button" href="https://t.me/prasatdigitalcambodia" target="_blank" rel="noopener">Telegram</a>
						<a class="button" href="https://www.facebook.com/prasatdigitalcambodia" target="_blank" rel="noopener">Facebook</a>
						<a class="button" href="tel:+85516777366">016 777 366</a>
					</div>
				</section>

				<section class="pd-card pd-story">
					<p class="pd-story__motto">&ldquo;<?php echo esc_html( self::MOTTO ); ?>&rdquo;</p>
					<p class="pd-story__by">Built with care, one plugin at a time. Made by <b>Prasat Digital</b> &middot; Founded by <b><?php echo esc_html( self::FOUNDER ); ?></b></p>
				</section>
			</div>
			<?php
		}
	}
}
