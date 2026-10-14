<?php
/**
 * History screen.
 *
 * Expects $fast_index_view from Fast_Index_Admin::render_history().
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

$fast_index_stats = $fast_index_view['stats'];
$fast_index_page  = (int) $fast_index_view['page'];
$fast_index_types = array(
	Fast_Index_Google_Client::TYPE_UPDATED => __( 'Updated', 'fast-index' ),
	Fast_Index_Google_Client::TYPE_DELETED => __( 'Deleted', 'fast-index' ),
);
?>
<div id="fi" class="content-area">

	<h1 class="title"><?php esc_html_e( 'Summary', 'fast-index' ); ?></h1>

	<table class="table table-striped wp-list-table widefat fixed striped table-view-list posts">
		<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Total Sent', 'fast-index' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Total Waiting', 'fast-index' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Sent Today', 'fast-index' ); ?></th>
		</tr>
		</thead>
		<tbody class="the-list">
		<tr>
			<td><?php echo esc_html( number_format_i18n( $fast_index_stats['sent'] ) ); ?></td>
			<td><?php echo esc_html( number_format_i18n( $fast_index_stats['waiting'] ) ); ?></td>
			<td><?php echo esc_html( number_format_i18n( $fast_index_stats['today'] ) ); ?></td>
		</tr>
		</tbody>
	</table>

	<br>
	<h1 class="title"><?php esc_html_e( 'History', 'fast-index' ); ?></h1>
	<br>

	<table class="table table-striped wp-list-table widefat fixed striped table-view-list posts">
		<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Title', 'fast-index' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Date', 'fast-index' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Send Status', 'fast-index' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Post', 'fast-index' ); ?></th>
		</tr>
		</thead>
		<tbody class="the-list">
		<?php if ( array() === $fast_index_view['entries'] ) : ?>
			<tr><td colspan="4"><?php esc_html_e( 'Nothing was sent yet.', 'fast-index' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $fast_index_view['entries'] as $fast_index_entry ) : ?>
			<tr>
				<td class="title column-title column-primary page-title">
					<strong>
						<?php if ( $fast_index_entry['post_id'] > 0 && get_post( $fast_index_entry['post_id'] ) ) : ?>
							<a class="row-title" href="<?php echo esc_url( (string) get_edit_post_link( $fast_index_entry['post_id'] ) ); ?>"><?php echo esc_html( $fast_index_entry['title'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $fast_index_entry['title'] ); ?>
						<?php endif; ?>
					</strong>
				</td>
				<td class="date column-date">
					<?php echo esc_html( isset( $fast_index_types[ $fast_index_entry['type'] ] ) ? $fast_index_types[ $fast_index_entry['type'] ] : $fast_index_entry['type'] ); ?><br>
					<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $fast_index_entry['timestamp'] ) ); ?>
				</td>
				<td class="fi-history-status<?php echo $fast_index_entry['code'] < 0 ? ' fi-history-status--not-sent' : ''; ?>">
					<?php /* Isolated, so the code and its name keep their order on right-to-left screens ("200 OK", not "OK 200"). */ ?>
					<bdi dir="auto"><?php echo esc_html( Fast_Index_Admin::status_label( $fast_index_entry['code'] ) ); ?></bdi>
					<?php if ( $fast_index_entry['retry'] ) : ?>
						<br><span class="description fi-history-retry"><?php echo esc_html( Fast_Index_Admin::retry_note() ); ?></span>
					<?php endif; ?>
				</td>
				<td><a class="row-title" href="<?php echo esc_url( $fast_index_entry['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Post', 'fast-index' ); ?></a></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<nav aria-label="<?php esc_attr_e( 'Pagination', 'fast-index' ); ?>">
		<ul class="pagination mt-3">
			<?php if ( $fast_index_page >= 1 ) : ?>
				<li class="page-item"><a class="page-link" href="<?php echo esc_url( add_query_arg( 'pn', $fast_index_page - 1, $fast_index_view['base_url'] ) ); ?>"><?php esc_html_e( 'Previous', 'fast-index' ); ?></a></li>
			<?php endif; ?>
			<?php if ( $fast_index_view['has_next'] ) : ?>
				<li class="page-item"><a class="page-link" href="<?php echo esc_url( add_query_arg( 'pn', $fast_index_page + 1, $fast_index_view['base_url'] ) ); ?>"><?php esc_html_e( 'Next', 'fast-index' ); ?></a></li>
			<?php endif; ?>
		</ul>
	</nav>

</div>
