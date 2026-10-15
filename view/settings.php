<?php
/**
 * Settings screen.
 *
 * Expects $fast_index_view from Fast_Index_Admin::render_settings().
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

$fast_index_settings = $fast_index_view['settings'];
$fast_index_help_url = admin_url( 'admin.php?page=' . Fast_Index_Help::SLUG );
?>
<div id="fi" class="content-area">

	<?php foreach ( $fast_index_view['messages'] as $fast_index_message ) : ?>
		<div class="<?php echo esc_attr( 'notice notice-' . $fast_index_message['type'] . ' is-dismissible' ); ?>">
			<p>
				<?php echo esc_html( $fast_index_message['message'] ); ?>
				<?php if ( '' !== $fast_index_message['link_url'] ) : ?>
					<a href="<?php echo esc_url( $fast_index_message['link_url'] ); ?>"><?php echo esc_html( $fast_index_message['link_text'] ); ?></a>
				<?php endif; ?>
			</p>
		</div>
	<?php endforeach; ?>

	<div class="pc">

		<div class="pw50">

			<h1 class="title"><?php echo esc_html_x( 'Settings', 'Heading of the Fast Index settings screen', 'fast-index' ); ?></h1>

			<?php if ( array() !== $fast_index_view['unused'] ) : ?>
				<div class="notice notice-info inline fi-unused">
					<p><?php esc_html_e( 'Some of your settings belong to the premium plan. They are kept, but this version does not use them. They are used again when the premium package is installed and its license is active.', 'fast-index' ); ?></p>
					<ul>
						<?php foreach ( $fast_index_view['unused'] as $fast_index_line ) : ?>
							<li><?php echo esc_html( $fast_index_line ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="form">

				<form method="post" class="settingsForm" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( Fast_Index_Admin_Actions::SAVE ); ?>" />
					<?php wp_nonce_field( Fast_Index_Admin_Actions::SAVE, Fast_Index_Admin_Actions::NONCE_FIELD ); ?>

					<table class="form-table" role="presentation">
						<tbody>

						<tr>
							<td scope="row">
								<label for="fast-index-status"><b><?php esc_html_e( 'Status', 'fast-index' ); ?></b></label> <br>
								<small><?php esc_html_e( 'Switch sending on or off.', 'fast-index' ); ?></small>
							</td>
							<td>
								<select id="fast-index-status" name="fast_index_options[status]">
									<option value="<?php echo esc_attr( Fast_Index_Settings::STATUS_ACTIVE ); ?>" <?php selected( $fast_index_settings['status'], Fast_Index_Settings::STATUS_ACTIVE ); ?>><?php esc_html_e( 'Active', 'fast-index' ); ?></option>
									<option value="<?php echo esc_attr( Fast_Index_Settings::STATUS_PASSIVE ); ?>" <?php selected( $fast_index_settings['status'], Fast_Index_Settings::STATUS_PASSIVE ); ?>><?php esc_html_e( 'Passive', 'fast-index' ); ?></option>
								</select>
								<?php foreach ( Fast_Index_Help_Content::field_notes( 'status' ) as $fast_index_note ) : ?>
									<p class="description"><?php echo esc_html( $fast_index_note ); ?></p>
								<?php endforeach; ?>
							</td>
						</tr>

						<tr>
							<td scope="row">
								<b><?php esc_html_e( 'Post Types', 'fast-index' ); ?></b> <br>
								<small><?php esc_html_e( 'Select at least one post type.', 'fast-index' ); ?></small>
								<?php if ( isset( $fast_index_view['hints']['post_type'] ) ) : ?>
									<?php Fast_Index_Upgrade::render_hint( $fast_index_view['hints']['post_type'], $fast_index_view['hints']['url'] ); ?>
								<?php endif; ?>
							</td>
							<td>
								<?php foreach ( $fast_index_view['post_types'] as $fast_index_name => $fast_index_label ) : ?>
									<label class="fast-index-choice">
										<input type="checkbox" name="<?php echo esc_attr( 'fast_index_options[post_type][' . $fast_index_name . ']' ); ?>" value="1" <?php checked( isset( $fast_index_settings['post_type'][ $fast_index_name ] ) ); ?> />
										<?php echo esc_html( $fast_index_label ); ?>
									</label>
								<?php endforeach; ?>
								<?php foreach ( Fast_Index_Help_Content::field_notes( 'post_type' ) as $fast_index_note ) : ?>
									<p class="description"><?php echo esc_html( $fast_index_note ); ?></p>
								<?php endforeach; ?>
							</td>
						</tr>

						<tr>
							<td scope="row">
								<b><?php esc_html_e( 'Post Events', 'fast-index' ); ?></b> <br>
								<small><?php esc_html_e( 'Choose when Google is notified.', 'fast-index' ); ?></small>
								<?php if ( isset( $fast_index_view['hints']['post_status'] ) ) : ?>
									<?php Fast_Index_Upgrade::render_hint( $fast_index_view['hints']['post_status'], $fast_index_view['hints']['url'] ); ?>
								<?php endif; ?>
							</td>
							<td>
								<?php foreach ( $fast_index_view['events'] as $fast_index_event => $fast_index_label ) : ?>
									<label class="fast-index-choice">
										<input type="checkbox" name="<?php echo esc_attr( 'fast_index_options[post_status][' . $fast_index_event . ']' ); ?>" value="1" <?php checked( isset( $fast_index_settings['post_status'][ $fast_index_event ] ) ); ?> />
										<?php echo esc_html( $fast_index_label ); ?>
									</label>
								<?php endforeach; ?>
								<?php foreach ( array_keys( $fast_index_view['events'] ) as $fast_index_event ) : ?>
									<p class="description"><?php echo esc_html( Fast_Index_Help_Content::event_description( $fast_index_event ) ); ?></p>
								<?php endforeach; ?>
							</td>
						</tr>

						<?php
						/**
						 * Fires inside the settings form table, before the "Delete data on uninstall" row. Print whole
						 * table rows; field names go under fast_index_options[...].
						 *
						 * @param array $settings Clean settings.
						 */
						do_action( 'fast_index_settings_rows', $fast_index_settings );
						?>

						<tr>
							<td scope="row">
								<label for="fast-index-delete-data"><b><?php esc_html_e( 'Delete data on uninstall', 'fast-index' ); ?></b></label> <br>
								<small><?php esc_html_e( 'Delete all plugin data (settings, keys and history) when the plugin is deleted. This cannot be undone.', 'fast-index' ); ?></small>
							</td>
							<td>
								<input id="fast-index-delete-data" type="checkbox" name="fast_index_options[delete_data]" value="1" <?php checked( $fast_index_settings['delete_data'] ); ?> />
								<?php foreach ( Fast_Index_Help_Content::field_notes( 'delete_data' ) as $fast_index_note ) : ?>
									<p class="description"><?php echo esc_html( $fast_index_note ); ?></p>
								<?php endforeach; ?>
							</td>
						</tr>

						<tr>
							<td colspan="2">
								<a target="_blank" rel="noopener noreferrer" href="https://wordpress.org/support/plugin/fast-index/reviews/"><?php esc_html_e( 'Rate Fast Index on WordPress.org', 'fast-index' ); ?></a>
							</td>
						</tr>

						<tr>
							<td scope="row">&nbsp;</td>
							<td>
								<input name="submit" class="button button-primary settingsSubmitButton" type="submit" value="<?php esc_attr_e( 'Save', 'fast-index' ); ?>" />
							</td>
						</tr>

						</tbody>
					</table>
				</form>

				<h1 class="title"><?php esc_html_e( 'Upload Google Service Account Key', 'fast-index' ); ?></h1>

				<?php if ( isset( $fast_index_view['hints']['keys'] ) ) : ?>
					<?php Fast_Index_Upgrade::render_hint( $fast_index_view['hints']['keys'], $fast_index_view['hints']['url'] ); ?>
				<?php endif; ?>

				<?php if ( ! $fast_index_view['openssl'] ) : ?>
					<div class="notice notice-error inline"><p><?php echo esc_html( Fast_Index_Google_Client::openssl_message() ); ?></p></div>
				<?php endif; ?>

				<?php if ( array() !== $fast_index_view['accounts'] ) : ?>
					<table class="form-table fast-index-accounts" role="presentation">
						<tbody>
						<?php foreach ( $fast_index_view['accounts'] as $fast_index_account ) : ?>
							<tr class="trBorder">
								<td scope="row" class="insideTd">
									<small><b><?php echo esc_html( $fast_index_account['email'] ); ?></b></small><br>
									<small>
										<?php
										/* translators: %s: ID of the service account key. */
										echo esc_html( sprintf( __( 'Key ID: %s', 'fast-index' ), '' !== $fast_index_account['key_id'] ? $fast_index_account['key_id'] : '-' ) );
										?>
									</small>
								</td>
								<td class="insideTd">
									<?php if ( ! $fast_index_view['several_keys'] && count( $fast_index_view['accounts'] ) > 1 ) : ?>
										<small class="fi-key-use"><?php echo esc_html( $fast_index_account['in_use'] ? __( 'Used for sending', 'fast-index' ) : __( 'Kept, not used by this version', 'fast-index' ) ); ?></small><br>
									<?php elseif ( $fast_index_view['several_keys'] && count( $fast_index_view['accounts'] ) > 1 ) : ?>
										<small class="fi-key-use"><?php echo esc_html( $fast_index_account['main'] ? __( 'Main key', 'fast-index' ) : __( 'Backup key: used only when Google rejects the key before it', 'fast-index' ) ); ?></small><br>
									<?php endif; ?>
									<bdi dir="auto"><?php echo esc_html( null === $fast_index_account['status'] ? __( 'Not used yet', 'fast-index' ) : Fast_Index_Admin::status_label( $fast_index_account['status'] ) ); ?></bdi>
									<?php if ( $fast_index_account['paused_until'] > 0 ) : ?>
										<br><small class="fi-key-paused"><?php echo esc_html( Fast_Index_Admin::pause_label( $fast_index_account['paused_until'] ) ); ?></small>
									<?php endif; ?>
									<form method="post" class="fast-index-inline-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="<?php echo esc_attr( Fast_Index_Admin_Actions::DELETE ); ?>" />
										<input type="hidden" name="fast_index_account" value="<?php echo esc_attr( $fast_index_account['id'] ); ?>" />
										<input type="hidden" name="<?php echo esc_attr( Fast_Index_Admin_Actions::NONCE_FIELD ); ?>" value="<?php echo esc_attr( wp_create_nonce( Fast_Index_Admin_Actions::DELETE ) ); ?>" />
										<button type="submit" class="button-link button-link-delete"><?php esc_html_e( 'Delete', 'fast-index' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( Fast_Index_Admin_Actions::UPLOAD ); ?>" />
					<input type="hidden" name="<?php echo esc_attr( Fast_Index_Admin_Actions::NONCE_FIELD ); ?>" value="<?php echo esc_attr( wp_create_nonce( Fast_Index_Admin_Actions::UPLOAD ) ); ?>" />
					<table class="form-table" role="presentation">
						<tbody>
						<tr>
							<td scope="row"><label for="fast-index-key-files"><b><?php esc_html_e( 'JSON Key File', 'fast-index' ); ?></b></label></td>
							<td>
								<input id="fast-index-key-files" class="jsonFileUpload" type="file" accept=".json,application/json" name="fast_index_key_files[]" <?php echo $fast_index_view['several_keys'] ? 'multiple' : ''; ?> />
								<p class="description"><?php esc_html_e( 'The key file is read and stored in the database; it is not saved in the uploads folder. Only the email address and the key ID are shown here.', 'fast-index' ); ?></p>
								<?php if ( ! $fast_index_view['several_keys'] ) : ?>
									<p class="description"><?php esc_html_e( 'A new key replaces the key that is used for sending.', 'fast-index' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>

						<tr>
							<td scope="row">&nbsp;</td>
							<td>
								<input class="button button-primary" type="submit" value="<?php esc_attr_e( 'Upload key', 'fast-index' ); ?>" />
							</td>
						</tr>
						</tbody>
					</table>
				</form>

			</div>

		</div>

		<div class="pw50">

			<h1 class="title"><?php esc_html_e( 'Help & FAQ', 'fast-index' ); ?></h1>

			<div>
				<p><?php esc_html_e( 'Setting up for the first time, or something does not work? Step-by-step instructions, an explanation of every setting, solutions to common problems and answers to frequently asked questions are in Help & FAQ.', 'fast-index' ); ?></p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $fast_index_help_url ); ?>"><?php esc_html_e( 'Open Help & FAQ', 'fast-index' ); ?></a>
				</p>
				<ul>
					<?php foreach ( Fast_Index_Help_Content::sections() as $fast_index_section => $fast_index_section_title ) : ?>
						<li><a href="<?php echo esc_url( $fast_index_help_url . '#fi-help-' . $fast_index_section ); ?>"><?php echo esc_html( $fast_index_section_title ); ?></a></li>
					<?php endforeach; ?>
				</ul>
				<p class="description"><?php esc_html_e( 'Fast Index sends your addresses to the Google Indexing API. A 200 answer means Google accepted the notification; Google does not promise to index the page.', 'fast-index' ); ?></p>
			</div>

			<?php if ( null !== $fast_index_view['premium_card'] ) : ?>
				<div class="fi-premium-card">
					<h2><?php echo esc_html_x( 'Premium', 'Heading of the box about the premium plan', 'fast-index' ); ?></h2>
					<p><?php esc_html_e( 'The premium plan is a separate package. What each version does:', 'fast-index' ); ?></p>
					<table class="fi-compare">
						<thead>
						<tr>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Feature', 'fast-index' ); ?></span></th>
							<th scope="col"><?php echo esc_html_x( 'Free', 'Column of the comparison table', 'fast-index' ); ?></th>
							<th scope="col"><?php echo esc_html_x( 'Premium', 'Column of the comparison table', 'fast-index' ); ?></th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ( $fast_index_view['premium_card']['rows'] as $fast_index_row ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $fast_index_row['label'] ); ?></th>
								<?php foreach ( array( $fast_index_row['free'], $fast_index_row['premium'] ) as $fast_index_cell ) : ?>
									<td>
										<?php if ( true === $fast_index_cell ) : ?>
											<span class="fi-yes" aria-hidden="true">&#10003;</span><span class="screen-reader-text"><?php esc_html_e( 'Included', 'fast-index' ); ?></span>
										<?php elseif ( false === $fast_index_cell ) : ?>
											<span class="fi-no" aria-hidden="true">&ndash;</span><span class="screen-reader-text"><?php esc_html_e( 'Not included', 'fast-index' ); ?></span>
										<?php else : ?>
											<?php echo esc_html( $fast_index_cell ); ?>
										<?php endif; ?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( '' !== $fast_index_view['premium_card']['url'] ) : ?>
						<p><a class="button" href="<?php echo esc_url( $fast_index_view['premium_card']['url'] ); ?>"><?php esc_html_e( 'Upgrade to Premium', 'fast-index' ); ?></a></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

		</div>

		<div class="fiClear"></div>

	</div>

</div>
