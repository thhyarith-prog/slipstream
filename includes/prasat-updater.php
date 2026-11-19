<?php
/**
 * Prasat Digital updater: lets WordPress update Prasat Digital plugins from their GitHub
 * releases, with the normal "Update available" notice, one-click update, "View details" and
 * automatic updates.
 *
 * How it works: each plugin's header says `Update URI: https://github.com/<owner>/<repo>`.
 * WordPress (5.8+) then asks the `update_plugins_github.com` filter instead of WordPress.org.
 * This file answers it from the repository's latest GitHub release: its tag is the version
 * and its attached ZIP (<slug>.zip or <slug>-<version>.zip) is the package. Answers are cached
 * for 6 hours per plugin to stay far below GitHub's API limits.
 *
 * Every Prasat Digital plugin ships this same file; keep it identical in all of them.
 *
 * @package Prasat_Updater
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Prasat_Updater', false ) ) {

	final class Prasat_Updater {

		const VERSION = '1.0.0';
		const CACHE   = 6 * HOUR_IN_SECONDS;

		/** @var array plugin_basename => [ repo, slug ] */
		private static $plugins = array();

		/**
		 * @param string $file Main plugin file (__FILE__).
		 * @param string $repo "owner/repository" on GitHub.
		 */
		public static function register( $file, $repo ) {
			$base                   = plugin_basename( $file );
			self::$plugins[ $base ] = array(
				'repo' => trim( $repo, '/' ),
				'slug' => dirname( $base ),
			);
			if ( 1 === count( self::$plugins ) ) {
				add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 3 );
				add_filter( 'plugins_api', array( __CLASS__, 'details' ), 20, 3 );
				add_action( 'upgrader_process_complete', array( __CLASS__, 'flush' ), 10, 2 );
				add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder' ), 10, 4 );
			}
		}

		/** The latest release of a repository, cached. Null when GitHub can't be reached. */
		public static function release( $repo, $force = false ) {
			$key    = 'prasat_rel_' . md5( $repo );
			$cached = $force ? false : get_site_transient( $key );
			if ( is_array( $cached ) ) {
				return $cached['ok'] ? $cached : null;
			}
			$res = wp_remote_get(
				'https://api.github.com/repos/' . $repo . '/releases/latest',
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept'     => 'application/vnd.github+json',
						'User-Agent' => 'Prasat-Updater/' . self::VERSION,
					),
				)
			);
			$code = (int) wp_remote_retrieve_response_code( $res );
			$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
			if ( 200 !== $code || ! is_array( $data ) || empty( $data['tag_name'] ) ) {
				// Remember the failure for an hour so a GitHub outage doesn't slow every admin page.
				set_site_transient( $key, array( 'ok' => false ), HOUR_IN_SECONDS );
				return null;
			}
			$slug    = basename( $repo );
			$version = ltrim( (string) $data['tag_name'], 'vV' );
			$package = '';
			foreach ( (array) ( isset( $data['assets'] ) ? $data['assets'] : array() ) as $asset ) {
				$name = isset( $asset['name'] ) ? (string) $asset['name'] : '';
				if ( preg_match( '/^' . preg_quote( $slug, '/' ) . '(-v?[0-9][0-9.]*)?\.zip$/i', $name ) ) {
					$package = (string) $asset['browser_download_url'];
					break;
				}
			}
			$out = array(
				'ok'        => true,
				'version'   => $version,
				'package'   => $package,
				'url'       => isset( $data['html_url'] ) ? (string) $data['html_url'] : 'https://github.com/' . $repo,
				'notes'     => isset( $data['body'] ) ? (string) $data['body'] : '',
				'published' => isset( $data['published_at'] ) ? (string) $data['published_at'] : '',
			);
			set_site_transient( $key, $out, self::CACHE );
			return $out;
		}

		/** update_plugins_github.com: tell WordPress about a newer release. */
		public static function check( $update, $plugin_data, $plugin_file ) {
			if ( ! isset( self::$plugins[ $plugin_file ] ) ) {
				return $update;
			}
			$p   = self::$plugins[ $plugin_file ];
			$rel = self::release( $p['repo'] );
			if ( ! $rel || '' === $rel['package'] ) {
				return $update;
			}
			$icon = 'https://raw.githubusercontent.com/' . $p['repo'] . '/main/assets/img/icon-' . preg_replace( '/-(security|backup)$/', '', $p['slug'] ) . '.png';
			return array(
				'id'           => 'https://github.com/' . $p['repo'],
				'slug'         => $p['slug'],
				'plugin'       => $plugin_file,
				'version'      => $rel['version'],
				'new_version'  => $rel['version'],
				'url'          => 'https://github.com/' . $p['repo'],
				'package'      => $rel['package'],
				'icons'        => array( '1x' => $icon, '2x' => $icon ),
				'requires_php' => isset( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : '',
				'requires'     => isset( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : '',
			);
		}

		/** "View details" in the update notice: show the release notes from GitHub. */
		public static function details( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
				return $result;
			}
			foreach ( self::$plugins as $file => $p ) {
				if ( $p['slug'] !== $args->slug ) {
					continue;
				}
				$rel = self::release( $p['repo'] );
				if ( ! $rel ) {
					return $result;
				}
				if ( ! function_exists( 'get_plugin_data' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$data  = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
				$notes = '' !== trim( $rel['notes'] ) ? $rel['notes'] : 'See the release on GitHub.';
				return (object) array(
					'name'          => $data['Name'],
					'slug'          => $p['slug'],
					'version'       => $rel['version'],
					'author'        => $data['Author'],
					'homepage'      => 'https://github.com/' . $p['repo'],
					'requires'      => $data['RequiresWP'],
					'requires_php'  => $data['RequiresPHP'],
					'last_updated'  => $rel['published'],
					'download_link' => $rel['package'],
					'sections'      => array(
						'description' => wpautop( esc_html( $data['Description'] ) ),
						'changelog'   => wpautop( esc_html( $notes ) ),
					),
				);
			}
			return $result;
		}

		/** After an update, forget cached answers so the next check is fresh. */
		public static function flush( $upgrader, $extra ) {
			if ( isset( $extra['type'] ) && 'plugin' === $extra['type'] ) {
				foreach ( self::$plugins as $p ) {
					delete_site_transient( 'prasat_rel_' . md5( $p['repo'] ) );
				}
			}
		}

		/**
		 * Keep the plugin folder name when a release ZIP's top folder is named differently
		 * (e.g. "bastion-security-1.2.0/"), so WordPress replaces the plugin instead of adding a copy.
		 */
		public static function fix_folder( $source, $remote_source, $upgrader, $extra = array() ) {
			global $wp_filesystem;
			if ( empty( $extra['plugin'] ) || ! isset( self::$plugins[ $extra['plugin'] ] ) || ! $wp_filesystem ) {
				return $source;
			}
			$want = trailingslashit( $remote_source ) . self::$plugins[ $extra['plugin'] ]['slug'] . '/';
			if ( untrailingslashit( $source ) === untrailingslashit( $want ) ) {
				return $source;
			}
			return $wp_filesystem->move( $source, $want, true ) ? $want : $source;
		}
	}
}
