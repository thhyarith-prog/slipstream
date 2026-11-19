<?php
/**
 * File and media optimizations.
 *
 * @package Slipstream
 */

defined( 'ABSPATH' ) || exit;

$s = Slipstream_Settings::all();

Slipstream_Admin::form_start( 'optimize' );
?>
<section class="pd-card">
	<h2><?php esc_html_e( 'HTML and JavaScript', 'slipstream' ); ?></h2>
	<p class="pd-lead"><?php esc_html_e( 'Scripts that load before the page is shown are the biggest cause of slow PageSpeed scores. Test your site after switching on Defer or Delay; if something stops working, add its file name to the exceptions.', 'slipstream' ); ?></p>
	<div class="pd-rows">
		<?php
		Slipstream_Admin::switch_row( 'minify_html', __( 'Minify HTML', 'slipstream' ), __( 'Removes comments and extra spaces from pages. Code and preformatted text are left exactly as they are.', 'slipstream' ) );
		Slipstream_Admin::switch_row(
			'defer_js',
			__( 'Defer JavaScript', 'slipstream' ),
			__( 'Scripts download while the page is shown and run right after, in their original order. jQuery is kept as is for theme compatibility.', 'slipstream' ),
			Slipstream_Admin::badge( __( 'Test after enabling', 'slipstream' ), 'warn' ),
			Slipstream_Admin::textarea( 'defer_exclude', __( 'Don\'t defer (file name or text in the script)', 'slipstream' ), "slider.js\nrecaptcha" )
		);
		Slipstream_Admin::switch_row(
			'delay_js',
			__( 'Delay JavaScript until interaction', 'slipstream' ),
			__( 'Scripts wait until the visitor moves the mouse, scrolls, taps or types. Biggest PageSpeed gain; menus or sliders that must work instantly may need an exception.', 'slipstream' ),
			Slipstream_Admin::badge( __( 'Biggest gain', 'slipstream' ), 'info' ),
			Slipstream_Admin::textarea( 'delay_exclude', __( 'Don\'t delay (file name or text in the script)', 'slipstream' ), "navigation.js\nconsent" ) . '<p class="pd-actions" style="margin-top:8px"><label for="ss-delay_timeout">' . esc_html__( 'Also run them after', 'slipstream' ) . '</label><input type="number" id="ss-delay_timeout" name="ss[delay_timeout]" min="0" max="60" class="small-text" value="' . esc_attr( $s['delay_timeout'] ) . '"><span class="pd-muted">' . esc_html__( 'seconds without interaction (0 = never)', 'slipstream' ) . '</span></p>'
		);
		?>
	</div>
</section>

<section class="pd-card">
	<h2><?php esc_html_e( 'Images and video', 'slipstream' ); ?></h2>
	<div class="pd-rows">
		<?php
		Slipstream_Admin::switch_row( 'lazy_media', __( 'Lazy-load images and embeds', 'slipstream' ), __( 'Images and iframes further down the page load only when the visitor scrolls near them. The first images are always loaded straight away.', 'slipstream' ) );
		Slipstream_Admin::switch_row( 'youtube_facade', __( 'YouTube click-to-load', 'slipstream' ), __( 'Shows the video thumbnail with a play button; YouTube\'s ~1 MB player loads only when it is clicked.', 'slipstream' ) );
		?>
	</div>
	<p class="description"><?php esc_html_e( 'To convert images to WebP and videos to WebM, use Featherweight by Prasat Digital.', 'slipstream' ); ?></p>
</section>

<section class="pd-card">
	<h2><?php esc_html_e( 'Connections and CDN', 'slipstream' ); ?></h2>
	<div class="pd-grid">
		<div><?php echo Slipstream_Admin::textarea( 'preconnect', __( 'Connect early to these domains', 'slipstream' ), "https://fonts.gstatic.com\nhttps://www.googletagmanager.com" ); // phpcs:ignore ?><p class="description"><?php esc_html_e( 'Third-party services your pages always use. The browser opens the connection while it reads the page.', 'slipstream' ); ?></p></div>
		<div><?php echo Slipstream_Admin::textarea( 'preload_fonts', __( 'Preload fonts (full URL)', 'slipstream' ), home_url( '/wp-content/themes/your-theme/fonts/main.woff2' ) ); // phpcs:ignore ?><p class="description"><?php esc_html_e( 'The one or two fonts used above the fold. Stops text from flashing.', 'slipstream' ); ?></p></div>
	</div>
	<label class="pd-label" for="ss-cdn_url"><?php esc_html_e( 'CDN address', 'slipstream' ); ?></label>
	<input type="url" id="ss-cdn_url" name="ss[cdn_url]" class="regular-text code" value="<?php echo esc_attr( $s['cdn_url'] ); ?>" placeholder="https://cdn.example.com">
	<p class="description"><?php esc_html_e( 'Images, CSS, JavaScript and fonts from wp-content and wp-includes are loaded from this address. Leave empty if you don\'t use a CDN (Cloudflare\'s proxy needs nothing here).', 'slipstream' ); ?></p>
</section>

<section class="pd-card">
	<h2><?php esc_html_e( 'Logged-in users', 'slipstream' ); ?></h2>
	<div class="pd-rows">
		<?php Slipstream_Admin::switch_row( 'optimize_logged_in', __( 'Also optimize for logged-in users', 'slipstream' ), __( 'Off by default, so you always see an unmodified page when you are logged in. Add ?slipstream_off to any URL to see a page without optimizations.', 'slipstream' ) ); ?>
	</div>
</section>
<?php
Slipstream_Admin::form_end();
