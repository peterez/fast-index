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
<div id="fi" class="wrap cdo-ui cdo-ui--fast-index">

	<?php Fast_Index_Admin::render_band( __( 'History', 'fast-index' ) ); ?>

	<?php foreach ( $fast_index_view['messages'] as $fast_index_message ) : ?>
		<div class="<?php echo esc_attr( 'notice notice-' . $fast_index_message['type'] . ' is-dismissible' ); ?>">
			<p><?php echo esc_html( $fast_index_message['message'] ); ?></p>
		</div>
	<?php endforeach; ?>

	<div class="cdo-stack">

	<section aria-labelledby="fi-summary-title">
		<h2 id="fi-summary-title" class="screen-reader-text"><?php esc_html_e( 'Summary', 'fast-index' ); ?></h2>
		<dl class="cdo-stats fi-summary">
			<div class="cdo-stat">
				<dt class="cdo-stat__label"><?php esc_html_e( 'Total Sent', 'fast-index' ); ?></dt>
				<dd class="cdo-stat__value"><?php echo esc_html( number_format_i18n( $fast_index_stats['sent'] ) ); ?></dd>
			</div>
			<div class="cdo-stat">
				<dt class="cdo-stat__label"><?php esc_html_e( 'Total Waiting', 'fast-index' ); ?></dt>
				<dd class="cdo-stat__value"><?php echo esc_html( number_format_i18n( $fast_index_stats['waiting'] ) ); ?></dd>
			</div>
			<div class="cdo-stat">
				<dt class="cdo-stat__label"><?php esc_html_e( 'Sent Today', 'fast-index' ); ?></dt>
				<dd class="cdo-stat__value"><?php echo esc_html( number_format_i18n( $fast_index_stats['today'] ) ); ?></dd>
			</div>
			<?php if ( $fast_index_view['indexnow'] ) : ?>
				<div class="cdo-stat">
					<dt class="cdo-stat__label"><?php esc_html_e( 'Sent to IndexNow Today', 'fast-index' ); ?></dt>
					<dd class="cdo-stat__value"><?php echo esc_html( number_format_i18n( $fast_index_stats['indexnow_today'] ) ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>

		<?php foreach ( $fast_index_view['upgrade'] as $fast_index_line ) : ?>
			<?php /* Inline, so WordPress does not move it to the top; the close button and the link remember the choice for this user. */ ?>
			<div class="notice notice-info inline is-dismissible fast-index-notice fi-upgrade-line" data-notice="<?php echo esc_attr( $fast_index_line['notice'] ); ?>" data-dismiss-url="<?php echo esc_url( Fast_Index_Notices::dismiss_url( $fast_index_line['notice'] ) ); ?>">
				<p>
					<?php echo esc_html( $fast_index_line['text'] ); ?>
					<a href="<?php echo esc_url( $fast_index_view['upgrade_url'] ); ?>"><?php esc_html_e( 'See the premium plan', 'fast-index' ); ?></a>
				</p>
				<p><a href="<?php echo esc_url( Fast_Index_Notices::dismiss_url( $fast_index_line['notice'] ) ); ?>"><?php esc_html_e( 'Do not show this again', 'fast-index' ); ?></a></p>
			</div>
		<?php endforeach; ?>
	</section>

	<?php
	/**
	 * Fires above the History list, below the summary.
	 *
	 * @param array $stats History counters (Fast_Index_Log::stats()).
	 */
	do_action( 'fast_index_history_before_table', $fast_index_view['stats'] );
	?>

	<div class="cdo-card cdo-card--flush">
	<div class="cdo-card__header">

	<?php if ( '' !== $fast_index_view['hint'] ) : ?>
		<div class="fi-index-status-hint">
			<b><?php esc_html_e( 'Index Status', 'fast-index' ); ?></b>
			<?php Fast_Index_Upgrade::render_hint( $fast_index_view['hint'], $fast_index_view['upgrade_url'] ); ?>
		</div>
	<?php endif; ?>

	<?php /* Channel filter: every channel, Google or IndexNow. */ ?>
	<ul class="cdo-filter fi-channel-filter">
		<?php
		$fast_index_filters = array_merge( array( '' => _x( 'All', 'History filter: every channel', 'fast-index' ) ), $fast_index_view['channels'] );
		?>
		<?php foreach ( $fast_index_filters as $fast_index_key => $fast_index_name ) : ?>
			<li>
				<a href="<?php echo esc_url( '' === $fast_index_key ? $fast_index_view['filter_url'] : add_query_arg( 'channel', $fast_index_key, $fast_index_view['filter_url'] ) ); ?>"<?php echo $fast_index_key === $fast_index_view['channel'] ? ' class="current" aria-current="page"' : ''; ?>><?php echo esc_html( $fast_index_name ); ?></a>
			</li>
		<?php endforeach; ?>
	</ul>
	</div>

	<?php
	// Column names are repeated in data-colname: on phone screens WordPress shows the title only, and the
	// toggle-row button opens the other columns of a row with these names as labels.
	$fast_index_columns = array(
		'date'    => __( 'Date', 'fast-index' ),
		'channel' => __( 'Channel', 'fast-index' ),
		'status'  => __( 'Send Status', 'fast-index' ),
		'post'    => __( 'Post', 'fast-index' ),
	);

	/**
	 * Filters the extra History columns, printed after the status column. Print their cells on
	 * fast_index_history_cell.
	 *
	 * @param string[] $columns Column name by key.
	 */
	$fast_index_extra = (array) apply_filters( 'fast_index_history_columns', array() );

	?>
	<table class="wp-list-table widefat fixed striped table-view-list posts cdo-table fi-history">
		<thead>
		<tr>
			<th scope="col" class="manage-column column-title column-primary"><?php esc_html_e( 'Title', 'fast-index' ); ?></th>
			<th scope="col" class="manage-column column-date"><?php echo esc_html( $fast_index_columns['date'] ); ?></th>
			<th scope="col" class="manage-column column-fi-channel"><?php echo esc_html( $fast_index_columns['channel'] ); ?></th>
			<th scope="col" class="manage-column column-fi-status"><?php echo esc_html( $fast_index_columns['status'] ); ?></th>
			<?php foreach ( $fast_index_extra as $fast_index_key => $fast_index_name ) : ?>
				<th scope="col" class="<?php echo esc_attr( 'manage-column column-fi-' . sanitize_key( $fast_index_key ) ); ?>"><?php echo esc_html( $fast_index_name ); ?></th>
			<?php endforeach; ?>
			<th scope="col" class="manage-column column-fi-post"><?php echo esc_html( $fast_index_columns['post'] ); ?></th>
		</tr>
		</thead>
		<tbody class="the-list">
		<?php if ( array() === $fast_index_view['entries'] ) : ?>
			<tr class="no-items"><td class="colspanchange" colspan="<?php echo esc_attr( (string) ( 5 + count( $fast_index_extra ) ) ); ?>"><?php esc_html_e( 'Nothing was sent yet.', 'fast-index' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $fast_index_view['entries'] as $fast_index_entry ) : ?>
			<tr>
				<td class="title column-title column-primary page-title" data-colname="<?php esc_attr_e( 'Title', 'fast-index' ); ?>">
					<strong>
						<?php if ( $fast_index_entry['post_id'] > 0 && get_post( $fast_index_entry['post_id'] ) ) : ?>
							<a class="row-title" href="<?php echo esc_url( (string) get_edit_post_link( $fast_index_entry['post_id'] ) ); ?>"><?php echo esc_html( $fast_index_entry['title'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $fast_index_entry['title'] ); ?>
						<?php endif; ?>
					</strong>
					<?php if ( $fast_index_entry['eligible'] ) : ?>
						<br><span class="fi-eligible cdo-badge cdo-badge--info cdo-badge--plain"><?php esc_html_e( 'Eligible for Google Indexing API', 'fast-index' ); ?></span>
					<?php endif; ?>
					<button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'fast-index' ); ?></span></button>
				</td>
				<td class="date column-date" data-colname="<?php echo esc_attr( $fast_index_columns['date'] ); ?>">
					<?php echo esc_html( isset( $fast_index_types[ $fast_index_entry['type'] ] ) ? $fast_index_types[ $fast_index_entry['type'] ] : $fast_index_entry['type'] ); ?><br>
					<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $fast_index_entry['timestamp'] ) ); ?>
				</td>
				<td class="column-fi-channel" data-colname="<?php echo esc_attr( $fast_index_columns['channel'] ); ?>"><?php echo esc_html( $fast_index_view['channels'][ $fast_index_entry['channel'] ] ); ?></td>
				<td class="column-fi-status fi-history-status<?php echo $fast_index_entry['code'] < 0 ? ' fi-history-status--not-sent' : ''; ?>" data-colname="<?php echo esc_attr( $fast_index_columns['status'] ); ?>">
					<?php /* Isolated, so the code and its name keep their order on right-to-left screens ("200 OK", not "OK 200"). */ ?>
					<span class="<?php echo esc_attr( 'cdo-badge ' . Fast_Index_Admin::status_tone( $fast_index_entry['code'] ) ); ?>"><bdi dir="auto"><?php echo esc_html( Fast_Index_Admin::status_label( $fast_index_entry['code'], $fast_index_entry['channel'] ) ); ?></bdi></span>
					<?php $fast_index_note = Fast_Index_Admin::status_note( $fast_index_entry['code'], $fast_index_entry['channel'] ); ?>
					<?php if ( '' !== $fast_index_note ) : ?>
						<br><span class="description fi-history-note"><?php echo esc_html( $fast_index_note ); ?></span>
					<?php endif; ?>
					<?php if ( $fast_index_entry['retry'] ) : ?>
						<br><span class="description fi-history-retry"><?php echo esc_html( Fast_Index_Admin::retry_note() ); ?></span>
					<?php endif; ?>
				</td>
				<?php foreach ( $fast_index_extra as $fast_index_key => $fast_index_name ) : ?>
					<td class="<?php echo esc_attr( 'column-fi-' . sanitize_key( $fast_index_key ) ); ?>" data-colname="<?php echo esc_attr( $fast_index_name ); ?>">
						<?php
						/**
						 * Prints the cell of an extra History column (fast_index_history_columns).
						 *
						 * @param string $column Column key.
						 * @param array  $entry  History entry (Fast_Index_Log::page()).
						 */
						do_action( 'fast_index_history_cell', (string) $fast_index_key, $fast_index_entry );
						?>
					</td>
				<?php endforeach; ?>
				<td class="column-fi-post" data-colname="<?php echo esc_attr( $fast_index_columns['post'] ); ?>"><a class="row-title" href="<?php echo esc_url( $fast_index_entry['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Post', 'fast-index' ); ?></a></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $fast_index_page >= 1 || $fast_index_view['has_next'] ) : ?>
		<nav class="cdo-card__footer" aria-label="<?php esc_attr_e( 'Pagination', 'fast-index' ); ?>">
			<ul class="pagination cdo-pagination">
				<?php if ( $fast_index_page >= 1 ) : ?>
					<li><a class="button" href="<?php echo esc_url( add_query_arg( 'pn', $fast_index_page - 1, $fast_index_view['base_url'] ) ); ?>"><?php esc_html_e( 'Previous', 'fast-index' ); ?></a></li>
				<?php endif; ?>
				<?php if ( $fast_index_view['has_next'] ) : ?>
					<li><a class="button" href="<?php echo esc_url( add_query_arg( 'pn', $fast_index_page + 1, $fast_index_view['base_url'] ) ); ?>"><?php esc_html_e( 'Next', 'fast-index' ); ?></a></li>
				<?php endif; ?>
			</ul>
		</nav>
	<?php endif; ?>
	</div>

	</div>

</div>
