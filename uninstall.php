<?php
/**
 * Uninstall: deactivation already removed the drop-in, the WP_CACHE line, the .htaccess rules
 * and the cached pages. This removes Slipstream's settings.
 *
 * @package Slipstream
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'slipstream_settings', 'slipstream_version', 'slipstream_preload', 'slipstream_last_speed', 'slipstream_db_last' ) as $slipstream_option ) {
	delete_option( $slipstream_option );
}
delete_transient( 'slipstream_stats' );
delete_transient( 'slipstream_welcome' );
foreach ( array( 'slipstream_cleanup', 'slipstream_db_cleanup', 'slipstream_preload_run' ) as $slipstream_hook ) {
	wp_clear_scheduled_hook( $slipstream_hook );
}
