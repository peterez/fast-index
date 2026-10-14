<?php
/**
 * Manual run screen.
 *
 * Expects $fast_index_view from Fast_Index_Admin::render_run().
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="fi" class="content-area">

	<h1 class="title"><?php esc_html_e( 'Send Old Posts Now', 'fast-index' ); ?></h1>

	<p><?php esc_html_e( 'Sends one batch of old posts now, within the daily limit set on the settings page. The same batch also runs every hour.', 'fast-index' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( Fast_Index_Admin_Actions::RUN ); ?>" />
		<?php wp_nonce_field( Fast_Index_Admin_Actions::RUN, Fast_Index_Admin_Actions::NONCE_FIELD ); ?>
		<?php submit_button( __( 'Send now', 'fast-index' ), 'primary', 'submit', false ); ?>
	</form>

	<?php if ( null !== $fast_index_view['results'] ) : ?>
		<br>
		<table class="table table-striped wp-list-table widefat fixed striped table-view-list posts">
			<tbody>
			<?php if ( array() === $fast_index_view['results'] ) : ?>
				<tr><td><?php esc_html_e( 'There are no posts to send now. Check your settings and the daily limit.', 'fast-index' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $fast_index_view['results'] as $fast_index_result ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( $fast_index_result['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $fast_index_result['url'] ); ?></a></td>
					<td><?php echo esc_html( Fast_Index_Admin::status_label( $fast_index_result['code'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

</div>
