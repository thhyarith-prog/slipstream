<?php
/**
 * Slipstream advanced-cache drop-in.
 *
 * WordPress loads this file very early when WP_CACHE is true. It sends a stored copy of the
 * page when one exists and is fresh; otherwise it returns and WordPress builds the page as
 * usual. It is removed when Slipstream is deactivated. If Slipstream's files are missing it
 * does nothing, so it can never take a site down.
 *
 * @package Slipstream
 */

defined( 'ABSPATH' ) || exit;

$slipstream_config = @include WP_CONTENT_DIR . '/cache/slipstream-config.php'; // phpcs:ignore
if ( is_array( $slipstream_config ) && ! empty( $slipstream_config['enabled'] ) && ! empty( $slipstream_config['lib'] ) && is_file( $slipstream_config['lib'] ) ) {
	require_once $slipstream_config['lib'];
	define( 'SLIPSTREAM_DROPIN', true );
	Slipstream_Serve::serve( $slipstream_config );
}
unset( $slipstream_config );
