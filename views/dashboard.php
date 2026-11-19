<?php
/**
 * Dashboard: speed test, cache stats and what is switched on.
 *
 * @package Slipstream
 */

defined( 'ABSPATH' ) || exit;

$s                       = Slipstream_Settings::all();
list( $pages, $bytes )   = Slipstream_Cache::stats();
$preload                 = Slipstream_Preload::state();
$speed                   = get_option( 'slipstream_last_speed' );
$db                      = Slipstream_Database::counts();
$clutter                 = array_sum( array_diff_key( $db, array( 'optimize' => 0 ) ) );
$early                   = Slipstream_Cache::dropin_active();
$server                  = Slipstream_Util::server();
$checks                  = array(
	array( $s['cache'], $early ? __( 'Page cache, served before WordPress loads', 'slipstream' ) : __( 'Page cache (served when the plugin loads)', 'slipstream' ) ),
	array( $s['cache'] && $s['preload'], __( 'Preload: pages are cached before visitors ask for them', 'slipstream' ) ),
	array( $s['browser_cache'] && Slipstream_Htaccess::present(), __( 'Browser caching and compression rules', 'slipstream' ) ),
	array( $s['minify_html'], __( 'HTML minify', 'slipstream' ) ),
	array( $s['lazy_media'], __( 'Lazy-load images and videos', 'slipstream' ) ),
	array( $s['youtube_facade'], __( 'YouTube click-to-load', 'slipstream' ) ),
	array( $s['defer_js'] || $s['delay_js'], $s['delay_js'] ? __( 'JavaScript delayed until interaction', 'slipstream' ) : __( 'JavaScript deferred', 'slipstream' ) ),
	array( $s['no_emoji'] && $s['no_embeds'], __( 'Unused WordPress scripts removed', 'slipstream' ) ),
	array( (bool) $s['cdn_url'], __( 'CDN', 'slipstream' ) ),
);
$on = count( array_filter( wp_list_pluck( $checks, 0 ) ) );
?>
<?php if ( 'foreign' === Slipstream_Cache::dropin_state() ) : ?>
	<p class="pd-note pd-note--warn"><?php esc_html_e( 'Another cache plugin owns wp-content/advanced-cache.php. Slipstream still serves cached pages, a little later in the request. Deactivate the other cache plugin to get the fastest mode.', 'slipstream' ); ?></p>
<?php elseif ( $s['cache'] && ! $early ) : ?>
	<p class="pd-note pd-note--info"><?php esc_html_e( 'Fastest mode needs the line define( \'WP_CACHE\', true ); in wp-config.php, which Slipstream could not add (the file is read-only). Pages are still cached and served.', 'slipstream' ); ?></p>
<?php endif; ?>

<div class="pd-grid pd-grid--wide">
	<section class="pd-card ss-speed" id="ss-speed">
		<h2><?php esc_html_e( 'Speed test', 'slipstream' ); ?></h2>
		<p class="pd-lead"><?php esc_html_e( 'Loads your home page from this server twice: built by WordPress, and served by Slipstream.', 'slipstream' ); ?></p>
		<div class="ss-speed__nums">
			<div class="ss-speed__num">
				<span class="ss-speed__label"><?php esc_html_e( 'Without Slipstream', 'slipstream' ); ?></span>
				<strong data-k="without"><?php echo $speed ? esc_html( number_format_i18n( $speed['without'] ) ) : '—'; ?></strong><span class="ss-speed__unit">ms</span>
			</div>
			<div class="ss-speed__num is-fast">
				<span class="ss-speed__label"><?php esc_html_e( 'With Slipstream', 'slipstream' ); ?></span>
				<strong data-k="with"><?php echo $speed ? esc_html( number_format_i18n( $speed['with'] ) ) : '—'; ?></strong><span class="ss-speed__unit">ms</span>
			</div>
			<div class="ss-speed__factor" data-k="factor"<?php echo $speed && $speed['factor'] ? '' : ' hidden'; ?>><?php echo $speed ? esc_html( sprintf( __( '%s× faster', 'slipstream' ), number_format_i18n( $speed['factor'], 1 ) ) ) : ''; ?></div>
		</div>
		<div class="ss-speed__bar" aria-hidden="true"><span data-k="bar" style="width:<?php echo $speed && $speed['without'] ? esc_attr( max( 2, min( 100, round( 100 * $speed['with'] / $speed['without'] ) ) ) ) : 100; ?>%"></span></div>
		<p class="pd-actions">
			<button type="button" class="button button-primary" data-act="speed"><?php esc_html_e( 'Run speed test', 'slipstream' ); ?></button>
			<span class="pd-muted" data-k="when" role="status"><?php echo $speed ? esc_html( sprintf( __( 'Last run %s ago', 'slipstream' ), human_time_diff( $speed['when'] ) ) ) : esc_html__( 'Not run yet.', 'slipstream' ); ?></span>
		</p>
		<p class="pd-note pd-note--warn" data-k="warn" role="alert" hidden></p>
	</section>

	<section class="pd-card">
		<h2><?php esc_html_e( 'What Slipstream is doing', 'slipstream' ); ?> <span class="pd-count"><?php echo (int) $on; ?>/<?php echo count( $checks ); ?></span></h2>
		<ul class="pd-checklist">
			<?php foreach ( $checks as $c ) : ?>
				<li class="<?php echo $c[0] ? '' : 'is-off'; ?>"><span class="pd-tick<?php echo $c[0] ? '' : ' pd-tick--off'; ?>" aria-hidden="true"><?php echo $c[0] ? '&#10003;' : ''; ?></span><?php echo esc_html( $c[1] ); ?><?php if ( ! $c[0] ) : ?><span class="screen-reader-text"> (<?php esc_html_e( 'off', 'slipstream' ); ?>)</span><?php endif; ?></li>
			<?php endforeach; ?>
		</ul>
		<p class="description"><a href="<?php echo esc_url( Slipstream_Admin::url( 'optimize' ) ); ?>"><?php esc_html_e( 'Turn on more optimizations →', 'slipstream' ); ?></a></p>
	</section>
</div>

<div class="pd-grid pd-grid--4">
	<div class="pd-stat"><span class="pd-stat__num" data-k="pages"><?php echo esc_html( number_format_i18n( $pages ) ); ?></span><span class="pd-stat__label"><?php esc_html_e( 'Pages cached', 'slipstream' ); ?></span><span class="pd-stat__sub" data-k="bytes"><?php echo esc_html( Slipstream_Util::bytes( $bytes ) ); ?></span></div>
	<div class="pd-stat"><span class="pd-stat__num"><span data-k="pdone"><?php echo (int) $preload['done']; ?></span><small class="pd-muted"> / <span data-k="ptotal"><?php echo (int) $preload['total']; ?></span></small></span><span class="pd-stat__label"><?php esc_html_e( 'Preloaded', 'slipstream' ); ?></span><div class="pd-progress" aria-hidden="true"><span data-k="pbar" style="width:<?php echo $preload['total'] ? esc_attr( round( 100 * $preload['done'] / $preload['total'] ) ) : 0; ?>%"></span></div><span class="pd-stat__sub" data-k="pstate"><?php echo ! empty( $preload['queue'] ) ? esc_html__( 'Running in the background', 'slipstream' ) : ( $preload['finished'] ? esc_html( sprintf( __( 'Finished %s ago', 'slipstream' ), human_time_diff( $preload['finished'] ) ) ) : esc_html__( 'Idle', 'slipstream' ) ); ?></span></div>
	<div class="pd-stat"><span class="pd-stat__num"><?php echo esc_html( number_format_i18n( $clutter ) ); ?></span><span class="pd-stat__label"><?php esc_html_e( 'Database clutter', 'slipstream' ); ?></span><span class="pd-stat__sub"><a href="<?php echo esc_url( Slipstream_Admin::url( 'database' ) ); ?>"><?php esc_html_e( 'Review and clean', 'slipstream' ); ?></a></span></div>
	<div class="pd-stat"><span class="pd-stat__num"><?php echo esc_html( (int) $s['lifespan_hours'] ? $s['lifespan_hours'] . ' h' : '∞' ); ?></span><span class="pd-stat__label"><?php esc_html_e( 'Cache lifespan', 'slipstream' ); ?></span><span class="pd-stat__sub"><?php esc_html_e( 'Pages are also cleared the moment they change.', 'slipstream' ); ?></span></div>
</div>

<section class="pd-card">
	<h2><?php esc_html_e( 'Quick actions', 'slipstream' ); ?></h2>
	<div class="pd-actions">
		<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=slipstream_clear&what=all' ), 'slipstream_clear' ) ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><?php esc_html_e( 'Clear all cache', 'slipstream' ); ?></a>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=slipstream_clear&what=preload' ), 'slipstream_clear' ) ); ?>"><span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e( 'Clear and preload', 'slipstream' ); ?></a>
		<a class="button" href="<?php echo esc_url( Slipstream_Admin::url( 'database' ) ); ?>"><span class="dashicons dashicons-database" aria-hidden="true"></span><?php esc_html_e( 'Clean the database', 'slipstream' ); ?></a>
	</div>
</section>
