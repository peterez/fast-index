<?php
/**
 * "Send Old Posts Now" screen.
 *
 * Expects $fast_index_results from Fast_Index_Premium_Backlog::render(): rows of the last run, or null.
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="fi" class="wrap cdo-ui cdo-ui--fast-index">

	<?php Fast_Index_Admin::render_band( __( 'Send Old Posts Now', 'fast-index' ), __( 'Sends one batch of old posts now, within the daily limit set on the settings screen. The same batch also runs every hour.', 'fast-index' ) ); ?>

	<div class="cdo-stack">

		<form class="cdo-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( Fast_Index_Premium_Backlog::RUN_ACTION ); ?>" />
			<?php wp_nonce_field( Fast_Index_Premium_Backlog::RUN_ACTION, Fast_Index_Admin_Actions::NONCE_FIELD ); ?>
			<p class="cdo-actions"><?php submit_button( __( 'Send now', 'fast-index' ), 'primary', 'submit', false ); ?></p>
		</form>

		<?php if ( null !== $fast_index_results ) : ?>
			<div class="cdo-card cdo-card--flush">
				<table class="wp-list-table widefat fixed striped cdo-table">
					<tbody>
					<?php if ( array() === $fast_index_results ) : ?>
						<tr><td><?php esc_html_e( 'There are no posts to send now. Check your settings and the daily limit.', 'fast-index' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $fast_index_results as $fast_index_result ) : ?>
						<tr>
							<td><a class="row-title" href="<?php echo esc_url( $fast_index_result['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $fast_index_result['url'] ); ?></a></td>
							<td><span class="<?php echo esc_attr( 'cdo-badge ' . Fast_Index_Admin::status_tone( $fast_index_result['code'] ) ); ?>"><bdi dir="auto"><?php echo esc_html( Fast_Index_Admin::status_label( $fast_index_result['code'] ) ); ?></bdi></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

	</div>

</div>
