<?php
/**
 * WordPress tweaks.
 *
 * @package Slipstream
 */

defined( 'ABSPATH' ) || exit;

$s = Slipstream_Settings::all();

Slipstream_Admin::form_start( 'tweaks' );
?>
<section class="pd-card">
	<h2><?php esc_html_e( 'Switch off what you don\'t use', 'slipstream' ); ?></h2>
	<p class="pd-lead"><?php esc_html_e( 'WordPress loads a few features on every page that most sites never use. Each one removed is one less file for visitors to download.', 'slipstream' ); ?></p>
	<div class="pd-rows">
		<?php
		Slipstream_Admin::switch_row( 'no_emoji', __( 'Remove the emoji script', 'slipstream' ), __( 'Browsers already show emoji. Saves a script and a style on every page.', 'slipstream' ) );
		Slipstream_Admin::switch_row( 'no_embeds', __( 'Remove WordPress embeds', 'slipstream' ), __( 'Stops other sites embedding your posts as cards and removes wp-embed.js. YouTube and other embeds in your posts keep working.', 'slipstream' ) );
		Slipstream_Admin::switch_row( 'no_dashicons', __( 'No admin icons for visitors', 'slipstream' ), __( 'The Dashicons font is only needed for the admin bar.', 'slipstream' ) );
		Slipstream_Admin::switch_row( 'clean_head', __( 'Tidy the page head', 'slipstream' ), __( 'Removes the WordPress version, RSD, Windows Live Writer and shortlink tags.', 'slipstream' ) );
		Slipstream_Admin::switch_row( 'no_self_pings', __( 'No self-pingbacks', 'slipstream' ), __( 'Linking to your own posts no longer creates pingback comments on them.', 'slipstream' ) );
		Slipstream_Admin::switch_row( 'no_jquery_migrate', __( 'Remove jQuery Migrate', 'slipstream' ), __( 'Only for up-to-date themes and plugins: old jQuery code may stop working.', 'slipstream' ), Slipstream_Admin::badge( __( 'Test after enabling', 'slipstream' ), 'warn' ) );
		?>
		<div class="pd-row">
			<div class="pd-row__text"><label class="pd-row__title" for="ss-heartbeat"><?php esc_html_e( 'Heartbeat', 'slipstream' ); ?></label><p class="pd-row__desc"><?php esc_html_e( 'WordPress checks in with the server every 15–60 seconds while the dashboard is open. Off on the public site; the post editor always keeps it (it saves drafts).', 'slipstream' ); ?></p></div>
			<div class="pd-row__control"><select id="ss-heartbeat" name="ss[heartbeat]">
				<option value="reduce" <?php selected( 'reduce', $s['heartbeat'] ); ?>><?php esc_html_e( 'Every 60 seconds', 'slipstream' ); ?></option>
				<option value="disable" <?php selected( 'disable', $s['heartbeat'] ); ?>><?php esc_html_e( 'Only in the editor', 'slipstream' ); ?></option>
				<option value="default" <?php selected( 'default', $s['heartbeat'] ); ?>><?php esc_html_e( 'WordPress default', 'slipstream' ); ?></option>
			</select></div>
		</div>
		<div class="pd-row">
			<div class="pd-row__text"><label class="pd-row__title" for="ss-revisions"><?php esc_html_e( 'Revisions to keep per post', 'slipstream' ); ?></label><p class="pd-row__desc"><?php echo Slipstream_Tweaks::revisions_in_config() ? esc_html__( 'Set in wp-config.php (WP_POST_REVISIONS), which takes priority.', 'slipstream' ) : esc_html__( 'WordPress keeps every revision forever. -1 = unlimited.', 'slipstream' ); ?></p></div>
			<div class="pd-row__control"><input type="number" id="ss-revisions" name="ss[revisions]" min="-1" max="100" class="small-text" value="<?php echo esc_attr( $s['revisions'] ); ?>" <?php disabled( Slipstream_Tweaks::revisions_in_config() ); ?>></div>
		</div>
	</div>
</section>
<?php
Slipstream_Admin::form_end();
