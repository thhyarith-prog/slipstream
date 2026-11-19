<?php
/**
 * Database clean-up.
 *
 * @package Slipstream
 */

defined( 'ABSPATH' ) || exit;

$s      = Slipstream_Settings::all();
$items  = Slipstream_Database::items();
$counts = Slipstream_Database::counts();
$last   = get_option( 'slipstream_db_last' );
?>
<section class="pd-card">
	<h2><?php esc_html_e( 'Clean the database', 'slipstream' ); ?></h2>
	<p class="pd-lead"><?php esc_html_e( 'A smaller database makes every uncached page and the dashboard faster. Nothing you can see on the site is removed.', 'slipstream' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ss-db-form" data-pd-confirm="<?php esc_attr_e( 'Permanently delete the selected items from the database? Consider making a backup first.', 'slipstream' ); ?>">
		<input type="hidden" name="action" value="slipstream_db_clean">
		<?php wp_nonce_field( 'slipstream_db_clean' ); ?>
		<div class="pd-table-wrap">
			<table class="pd-table">
				<thead><tr><th style="width:36px"><span class="screen-reader-text"><?php esc_html_e( 'Select', 'slipstream' ); ?></span></th><th><?php esc_html_e( 'Item', 'slipstream' ); ?></th><th class="num"><?php esc_html_e( 'Found', 'slipstream' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $items as $key => $it ) : ?>
					<?php $n = isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0; ?>
					<tr>
						<td><input type="checkbox" name="items[]" value="<?php echo esc_attr( $key ); ?>" id="ss-db-<?php echo esc_attr( $key ); ?>" <?php checked( $n > 0 ); ?> <?php disabled( 0 === $n ); ?>></td>
						<td><label for="ss-db-<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $it[0] ); ?></strong><br><span class="pd-muted"><?php echo esc_html( $it[1] ); ?></span></label></td>
						<td class="num"><?php echo $n ? '<span class="pd-badge pd-badge--warn">' . esc_html( number_format_i18n( $n ) ) . '</span>' : '<span class="pd-badge pd-badge--ok">' . esc_html__( 'Clean', 'slipstream' ) . '</span>'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="pd-actions ss-gap-top">
			<button class="button button-primary" data-db-clean><?php esc_html_e( 'Clean selected', 'slipstream' ); ?></button>
			<?php if ( $last ) : ?>
				<span class="pd-muted"><?php echo esc_html( sprintf( /* translators: 1: time ago, 2: count */ __( 'Last cleaned %1$s ago: %2$s items removed.', 'slipstream' ), human_time_diff( $last['time'] ), number_format_i18n( array_sum( (array) $last['done'] ) ) ) ); ?></span>
			<?php endif; ?>
		</p>
	</form>
</section>

<?php Slipstream_Admin::form_start( 'database' ); ?>
<section class="pd-card">
	<h2><?php esc_html_e( 'Automatic clean-up', 'slipstream' ); ?></h2>
	<label class="pd-label" for="ss-db_schedule"><?php esc_html_e( 'Clean automatically', 'slipstream' ); ?></label>
	<select id="ss-db_schedule" name="ss[db_schedule]">
		<option value="off" <?php selected( 'off', $s['db_schedule'] ); ?>><?php esc_html_e( 'Never', 'slipstream' ); ?></option>
		<option value="weekly" <?php selected( 'weekly', $s['db_schedule'] ); ?>><?php esc_html_e( 'Every week', 'slipstream' ); ?></option>
		<option value="monthly" <?php selected( 'monthly', $s['db_schedule'] ); ?>><?php esc_html_e( 'Every month', 'slipstream' ); ?></option>
	</select>
	<p class="pd-label ss-gap-top" id="ss-what-h"><?php esc_html_e( 'What to clean', 'slipstream' ); ?></p>
	<div class="pd-picks" role="group" aria-labelledby="ss-what-h">
		<?php foreach ( $items as $key => $it ) : ?>
			<label class="pd-pick"><input type="checkbox" name="ss[db_items][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, (array) $s['db_items'], true ) ); ?>><span><?php echo esc_html( $it[0] ); ?></span></label>
		<?php endforeach; ?>
	</div>
</section>
<?php
Slipstream_Admin::form_end();
