<?php
/**
 * Cache settings.
 *
 * @package Slipstream
 */

defined( 'ABSPATH' ) || exit;

$s      = Slipstream_Settings::all();
$server = Slipstream_Util::server();
$early  = Slipstream_Cache::dropin_active();

Slipstream_Admin::form_start( 'cache' );
?>
<section class="pd-card">
	<h2><?php esc_html_e( 'Page cache', 'slipstream' ); ?></h2>
	<p class="pd-lead"><?php esc_html_e( 'Visitors get a ready-made copy of each page instead of WordPress building it every time. Logged-in users, shopping carts and checkout always get live pages.', 'slipstream' ); ?></p>
	<div class="pd-rows">
		<?php
		Slipstream_Admin::switch_row(
			'cache',
			__( 'Cache pages', 'slipstream' ),
			$early ? __( 'Running in the fastest mode: cached pages are sent before WordPress even starts.', 'slipstream' ) : __( 'Cached pages are sent as soon as Slipstream loads.', 'slipstream' ),
			Slipstream_Admin::badge( __( 'Recommended', 'slipstream' ), 'ok' ),
			'<label class="pd-label" for="ss-lifespan_hours">' . esc_html__( 'Rebuild pages after', 'slipstream' ) . '</label><span class="pd-actions"><input type="number" id="ss-lifespan_hours" name="ss[lifespan_hours]" min="0" max="720" class="small-text" value="' . esc_attr( $s['lifespan_hours'] ) . '"> <span class="pd-muted">' . esc_html__( 'hours (0 = only when content changes)', 'slipstream' ) . '</span></span>'
		);
		Slipstream_Admin::switch_row( 'preload', __( 'Preload', 'slipstream' ), __( 'After the cache is cleared, Slipstream visits your pages in the background so the first visitor already gets a fast page.', 'slipstream' ), Slipstream_Admin::badge( __( 'Recommended', 'slipstream' ), 'ok' ) );
		Slipstream_Admin::switch_row( 'mobile_cache', __( 'Separate cache for phones', 'slipstream' ), __( 'Only needed if your theme shows different HTML to phones (most themes are responsive and don\'t).', 'slipstream' ) );
		?>
	</div>
</section>

<section class="pd-card">
	<h2><?php esc_html_e( 'Browser caching and compression', 'slipstream' ); ?></h2>
	<p class="pd-lead"><?php esc_html_e( 'Tells browsers to keep images, CSS and scripts for a year and compresses text files, so repeat visits load almost nothing.', 'slipstream' ); ?></p>
	<div class="pd-rows">
		<?php
		if ( Slipstream_Htaccess::supported() ) {
			$state = Slipstream_Htaccess::present() ? Slipstream_Admin::badge( __( 'Rules active', 'slipstream' ), 'ok' ) : '';
			Slipstream_Admin::switch_row( 'browser_cache', __( 'Add browser caching rules to .htaccess', 'slipstream' ), __( 'Written between "# BEGIN Slipstream" markers and removed cleanly when switched off.', 'slipstream' ), $state );
		} else {
			$snippet = '<p class="pd-row__desc">' . esc_html__( 'Ask your host to add this to the Nginx server block (or paste it yourself):', 'slipstream' ) . '</p><textarea class="code" rows="6" readonly>' . esc_textarea( Slipstream_Htaccess::nginx_snippet() ) . '</textarea>';
			Slipstream_Admin::switch_row( 'browser_cache', __( 'Browser caching rules', 'slipstream' ), sprintf( /* translators: %s: server name */ __( 'This server (%s) doesn\'t read .htaccess files, so the rules can\'t be added automatically.', 'slipstream' ), ucfirst( $server ) ), Slipstream_Admin::badge( __( 'Manual step', 'slipstream' ), 'warn' ), $snippet );
		}
		?>
	</div>
</section>

<section class="pd-card">
	<h2><?php esc_html_e( 'Never cache', 'slipstream' ); ?></h2>
	<p class="pd-lead"><?php esc_html_e( 'One per line. WooCommerce cart, checkout and account pages, logged-in users and password-protected posts are always excluded.', 'slipstream' ); ?></p>
	<div class="pd-grid">
		<div><?php echo Slipstream_Admin::textarea( 'exclude_paths', __( 'Pages (path or wildcard)', 'slipstream' ), "members\n/landing/*" ); // phpcs:ignore ?><p class="description"><?php esc_html_e( 'A path like members covers /members/ and everything under it.', 'slipstream' ); ?></p></div>
		<div><?php echo Slipstream_Admin::textarea( 'exclude_cookies', __( 'Visitors with these cookies', 'slipstream' ), 'my_membership_' ); // phpcs:ignore ?><p class="description"><?php esc_html_e( 'Cookie name or the start of it.', 'slipstream' ); ?></p></div>
		<div><?php echo Slipstream_Admin::textarea( 'exclude_agents', __( 'Browsers / bots (user agent)', 'slipstream' ), 'Lighthouse' ); // phpcs:ignore ?></div>
		<div><?php echo Slipstream_Admin::textarea( 'ignore_query', __( 'Ignore these URL parameters', 'slipstream' ), "utm_*\nfbclid" ); // phpcs:ignore ?><p class="description"><?php esc_html_e( 'Tracking links like ?utm_source= still get the cached page. Any other parameter skips the cache.', 'slipstream' ); ?></p></div>
	</div>
</section>
<?php
Slipstream_Admin::form_end();
