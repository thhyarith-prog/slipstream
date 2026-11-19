<?php
/**
 * Database clean-up: old revisions, drafts nobody finished, trash, spam and expired
 * temporary data. Each item shows how many rows it would remove before anything is deleted.
 *
 * @package Slipstream
 */

defined( 'ABSPATH' ) || exit;

class Slipstream_Database {

	const HOOK = 'slipstream_db_cleanup';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'scheduled' ) );
		add_action( 'slipstream_settings_updated', array( __CLASS__, 'reschedule' ) );
	}

	public static function items() {
		return array(
			'revisions'      => array( __( 'Post revisions', 'slipstream' ), __( 'Older copies saved every time a post was edited. The current version is never touched.', 'slipstream' ) ),
			'auto_drafts'    => array( __( 'Auto-drafts', 'slipstream' ), __( 'Empty drafts WordPress creates when "Add new" is opened.', 'slipstream' ) ),
			'trash_posts'    => array( __( 'Posts in the trash', 'slipstream' ), __( 'Posts and pages already moved to the trash.', 'slipstream' ) ),
			'spam_comments'  => array( __( 'Spam comments', 'slipstream' ), __( 'Comments marked as spam.', 'slipstream' ) ),
			'trash_comments' => array( __( 'Comments in the trash', 'slipstream' ), __( 'Comments already moved to the trash.', 'slipstream' ) ),
			'transients'     => array( __( 'Expired temporary data', 'slipstream' ), __( 'Cached values ("transients") whose time is up.', 'slipstream' ) ),
			'orphan_meta'    => array( __( 'Orphaned post data', 'slipstream' ), __( 'Extra fields left behind by posts that no longer exist.', 'slipstream' ) ),
			'optimize'       => array( __( 'Optimize tables', 'slipstream' ), __( 'Reclaims the space freed by the clean-up (MySQL / MariaDB).', 'slipstream' ) ),
		);
	}

	private static function is_mysql() {
		global $wpdb;
		return ! ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) && ! ( isset( $wpdb->dbh ) && is_object( $wpdb->dbh ) && false !== stripos( get_class( $wpdb->dbh ), 'sqlite' ) ) && ! class_exists( 'WP_SQLite_DB', false );
	}

	/** How many rows each item would remove. */
	public static function counts() {
		global $wpdb;
		$now = time();
		$c   = array(
			'revisions'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" ),
			'auto_drafts'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ),
			'trash_posts'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" ),
			'spam_comments'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" ),
			'trash_comments' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" ),
			'transients'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', $now ) ),
			'orphan_meta'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" ),
			'optimize'       => self::is_mysql() ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME LIKE %s AND DATA_FREE > 0', DB_NAME, $wpdb->esc_like( $wpdb->prefix ) . '%' ) ) : 0,
		);
		return $c;
	}

	/** Clean the chosen items. Returns rows removed per item. */
	public static function clean( array $items ) {
		global $wpdb;
		$done  = array();
		$items = array_intersect( array_keys( self::items() ), $items );
		foreach ( $items as $item ) {
			switch ( $item ) {
				case 'revisions':
					$ids           = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' LIMIT 5000" );
					$done[ $item ] = self::delete_posts( $ids );
					break;
				case 'auto_drafts':
					$ids           = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft' LIMIT 5000" );
					$done[ $item ] = self::delete_posts( $ids );
					break;
				case 'trash_posts':
					$ids           = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash' LIMIT 2000" );
					$done[ $item ] = self::delete_posts( $ids );
					break;
				case 'spam_comments':
				case 'trash_comments':
					$status = 'spam_comments' === $item ? 'spam' : 'trash';
					$ids    = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = %s LIMIT 5000", $status ) );
					$n      = 0;
					foreach ( $ids as $id ) {
						$n += wp_delete_comment( (int) $id, true ) ? 1 : 0;
					}
					$done[ $item ] = $n;
					break;
				case 'transients':
					$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d LIMIT 5000", $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) );
					foreach ( $names as $name ) {
						delete_transient( substr( $name, strlen( '_transient_timeout_' ) ) );
					}
					$done[ $item ] = count( $names );
					break;
				case 'orphan_meta':
					$done[ $item ] = self::is_mysql()
						? (int) $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" )
						: (int) $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})" ); // SQLite: no multi-table DELETE.
					break;
				case 'optimize':
					$n = 0;
					if ( self::is_mysql() ) {
						$tables = $wpdb->get_col( $wpdb->prepare( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME LIKE %s AND DATA_FREE > 0', DB_NAME, $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
						foreach ( $tables as $t ) {
							$wpdb->query( 'OPTIMIZE TABLE `' . esc_sql( $t ) . '`' ); // phpcs:ignore
							$n++;
						}
					}
					$done[ $item ] = $n;
					break;
			}
		}
		update_option( 'slipstream_db_last', array( 'time' => time(), 'done' => $done ), false );
		return $done;
	}

	/** wp_delete_post keeps every hook (and other plugins' clean-up) working. */
	private static function delete_posts( array $ids ) {
		$n = 0;
		foreach ( $ids as $id ) {
			$n += wp_delete_post( (int) $id, true ) ? 1 : 0;
		}
		return $n;
	}

	public static function reschedule() {
		wp_clear_scheduled_hook( self::HOOK );
		$when = Slipstream_Settings::get( 'db_schedule' );
		if ( 'off' !== $when ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly' === $when ? 'weekly' : 'slipstream_monthly', self::HOOK );
		}
	}

	public static function scheduled() {
		self::clean( (array) Slipstream_Settings::get( 'db_items' ) );
	}
}
