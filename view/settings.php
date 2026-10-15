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
<div id="fi" class="wrap cdo-ui cdo-ui--fast-index">

	<?php Fast_Index_Admin::render_band( _x( 'Settings', 'Heading of the Fast Index settings screen', 'fast-index' ) ); ?>

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

	<div class="cdo-intro fi-intro">
		<p class="cdo-intro__lead"><?php esc_html_e( 'This screen decides what Fast Index sends to Google and when. Upload your Google key, choose the content to send and save; after that every new post is sent on its own.', 'fast-index' ); ?></p>
	</div>

	<div class="cdo-layout">

		<div class="cdo-main cdo-stack">

			<section class="cdo-card">

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

				<form method="post" class="settingsForm" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( Fast_Index_Admin_Actions::SAVE ); ?>" />
					<?php wp_nonce_field( Fast_Index_Admin_Actions::SAVE, Fast_Index_Admin_Actions::NONCE_FIELD ); ?>

					<table class="form-table cdo-form-table" role="presentation">
						<tbody>

						<tr>
							<th scope="row">
								<label for="fast-index-status"><?php esc_html_e( 'Status', 'fast-index' ); ?></label>
								<span class="cdo-field-hint"><?php esc_html_e( 'Switch sending on or off.', 'fast-index' ); ?></span>
								<?php Fast_Index_Admin::render_whatis( 'status' ); ?>
							</th>
							<td>
								<div class="cdo-select">
								<select id="fast-index-status" name="fast_index_options[status]">
									<option value="<?php echo esc_attr( Fast_Index_Settings::STATUS_ACTIVE ); ?>" <?php selected( $fast_index_settings['status'], Fast_Index_Settings::STATUS_ACTIVE ); ?>><?php esc_html_e( 'Active', 'fast-index' ); ?></option>
									<option value="<?php echo esc_attr( Fast_Index_Settings::STATUS_PASSIVE ); ?>" <?php selected( $fast_index_settings['status'], Fast_Index_Settings::STATUS_PASSIVE ); ?>><?php esc_html_e( 'Passive', 'fast-index' ); ?></option>
								</select>
								</div>
								<?php foreach ( Fast_Index_Help_Content::field_notes( 'status' ) as $fast_index_note ) : ?>
									<p class="description"><?php echo esc_html( $fast_index_note ); ?></p>
								<?php endforeach; ?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Post Types', 'fast-index' ); ?>
								<span class="cdo-field-hint"><?php esc_html_e( 'Select at least one post type.', 'fast-index' ); ?></span>
								<?php Fast_Index_Admin::render_whatis( 'post_type' ); ?>
							</th>
							<td>
								<div class="cdo-choices">
									<?php foreach ( $fast_index_view['post_types'] as $fast_index_name => $fast_index_label ) : ?>
										<label class="fast-index-choice">
											<input class="cdo-check__input" type="checkbox" name="<?php echo esc_attr( 'fast_index_options[post_type][' . $fast_index_name . ']' ); ?>" value="1" <?php checked( isset( $fast_index_settings['post_type'][ $fast_index_name ] ) ); ?> />
											<?php echo esc_html( $fast_index_label ); ?>
										</label>
									<?php endforeach; ?>
								</div>
								<?php foreach ( Fast_Index_Help_Content::field_notes( 'post_type' ) as $fast_index_note ) : ?>
									<p class="description"><?php echo esc_html( $fast_index_note ); ?></p>
								<?php endforeach; ?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Post Events', 'fast-index' ); ?>
								<span class="cdo-field-hint"><?php esc_html_e( 'Choose when Google is notified.', 'fast-index' ); ?></span>
								<?php Fast_Index_Admin::render_whatis( 'post_status' ); ?>
							</th>
							<td>
								<div class="cdo-choices">
									<?php foreach ( $fast_index_view['events'] as $fast_index_event => $fast_index_label ) : ?>
										<label class="fast-index-choice">
											<input class="cdo-check__input" type="checkbox" name="<?php echo esc_attr( 'fast_index_options[post_status][' . $fast_index_event . ']' ); ?>" value="1" <?php checked( isset( $fast_index_settings['post_status'][ $fast_index_event ] ) ); ?> />
											<?php echo esc_html( $fast_index_label ); ?>
										</label>
									<?php endforeach; ?>
								</div>
								<?php foreach ( array_keys( $fast_index_view['events'] ) as $fast_index_event ) : ?>
									<p class="description"><?php echo esc_html( Fast_Index_Help_Content::event_description( $fast_index_event ) ); ?></p>
								<?php endforeach; ?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Supported Pages Only', 'fast-index' ); ?>
								<span class="cdo-field-hint"><?php esc_html_e( 'Pages that Google supports in the Indexing API.', 'fast-index' ); ?></span>
								<?php Fast_Index_Admin::render_whatis( 'eligible_only' ); ?>
							</th>
							<td>
								<label class="fast-index-choice cdo-choice">
									<input id="fast-index-eligible-only" class="cdo-check__input" type="checkbox" name="fast_index_options[eligible_only]" value="1" <?php checked( $fast_index_settings['eligible_only'] ); ?> />
									<span><?php esc_html_e( 'Send to Google only pages with JobPosting or BroadcastEvent data', 'fast-index' ); ?></span>
								</label>
								<?php foreach ( Fast_Index_Help_Content::field_notes( 'eligible_only' ) as $fast_index_note ) : ?>
									<p class="description"><?php echo esc_html( $fast_index_note ); ?></p>
								<?php endforeach; ?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php echo esc_html( 'IndexNow' ); ?>
								<span class="cdo-field-hint"><?php esc_html_e( 'Search engines other than Google.', 'fast-index' ); ?></span>
								<?php Fast_Index_Admin::render_whatis( 'indexnow' ); ?>
							</th>
							<td>
								<label class="fast-index-choice cdo-choice">
									<input id="fast-index-indexnow" class="cdo-check__input" type="checkbox" name="fast_index_options[indexnow]" value="1" <?php checked( $fast_index_settings['indexnow'] ); ?> />
									<span><?php esc_html_e( 'Also send to IndexNow', 'fast-index' ); ?></span>
								</label>
								<?php foreach ( Fast_Index_Help_Content::field_notes( 'indexnow' ) as $fast_index_note ) : ?>
									<p class="description"><?php echo esc_html( $fast_index_note ); ?></p>
								<?php endforeach; ?>
								<?php if ( '' !== $fast_index_view['indexnow_key_url'] ) : ?>
									<p class="description fi-indexnow-key">
										<?php
										printf(
											/* translators: %s: address of the IndexNow key file. */
											esc_html__( 'Key file: %s', 'fast-index' ),
											'<a href="' . esc_url( $fast_index_view['indexnow_key_url'] ) . '" target="_blank" rel="noopener noreferrer"><bdi dir="ltr">' . esc_html( $fast_index_view['indexnow_key_url'] ) . '</bdi></a>'
										);
										?>
									</p>
								<?php endif; ?>
							</td>
						</tr>

						<?php if ( isset( $fast_index_view['hints']['old_posts'], $fast_index_view['hints']['exclude_category'] ) ) : ?>
							<?php /* What the premium plan adds here: a name and a badge that explains it, no field. */ ?>
							<tr class="fi-premium-row">
								<th scope="row"><?php esc_html_e( 'Daily Limit for Old Posts', 'fast-index' ); ?></th>
								<td><?php Fast_Index_Upgrade::render_hint( $fast_index_view['hints']['old_posts'], $fast_index_view['hints']['url'] ); ?></td>
							</tr>
							<tr class="fi-premium-row">
								<th scope="row"><?php esc_html_e( 'Exclude Categories', 'fast-index' ); ?></th>
								<td><?php Fast_Index_Upgrade::render_hint( $fast_index_view['hints']['exclude_category'], $fast_index_view['hints']['url'] ); ?></td>
							</tr>
						<?php endif; ?>

						<?php
						/**
						 * Fires inside the settings form table, before the "Delete data on uninstall" row. Print whole
						 * table rows; field names go under fast_index_options[...].
						 *
						 * @param array $settings Clean settings.
						 */
						do_action( 'fast_index_settings_rows', $fast_index_settings );
						?>

						<tr class="cdo-row--danger">
							<th scope="row">
								<label for="fast-index-delete-data"><?php esc_html_e( 'Delete data on uninstall', 'fast-index' ); ?></label>
								<?php Fast_Index_Admin::render_whatis( 'delete_data' ); ?>
							</th>
							<td>
								<label class="cdo-choice">
									<input id="fast-index-delete-data" class="cdo-check__input" type="checkbox" name="fast_index_options[delete_data]" value="1" <?php checked( $fast_index_settings['delete_data'] ); ?> />
									<span><?php esc_html_e( 'Delete all plugin data (settings, keys and history) when the plugin is deleted. This cannot be undone.', 'fast-index' ); ?></span>
								</label>
								<?php foreach ( Fast_Index_Help_Content::field_notes( 'delete_data' ) as $fast_index_note ) : ?>
									<p class="description"><?php echo esc_html( $fast_index_note ); ?></p>
								<?php endforeach; ?>
							</td>
						</tr>

						</tbody>
					</table>

					<p class="cdo-actions">
						<input name="submit" class="button button-primary settingsSubmitButton" type="submit" value="<?php esc_attr_e( 'Save', 'fast-index' ); ?>" />
					</p>
				</form>
			</section>

			<section class="cdo-card" aria-labelledby="fi-keys-title">
				<div class="cdo-card__header">
					<h2 id="fi-keys-title" class="cdo-card__title"><?php esc_html_e( 'Upload Google Service Account Key', 'fast-index' ); ?></h2>
					<?php if ( isset( $fast_index_view['hints']['keys'] ) ) : ?>
						<?php Fast_Index_Upgrade::render_hint( $fast_index_view['hints']['keys'], $fast_index_view['hints']['url'] ); ?>
					<?php endif; ?>
				</div>

				<?php if ( ! $fast_index_view['openssl'] ) : ?>
					<div class="notice notice-error inline"><p><?php echo esc_html( Fast_Index_Google_Client::openssl_message() ); ?></p></div>
				<?php endif; ?>

				<?php if ( array() !== $fast_index_view['accounts'] ) : ?>
					<table class="form-table cdo-form-table fast-index-accounts" role="presentation">
						<tbody>
						<?php foreach ( $fast_index_view['accounts'] as $fast_index_account ) : ?>
							<tr>
								<th scope="row">
									<bdi dir="ltr"><?php echo esc_html( $fast_index_account['email'] ); ?></bdi>
									<span class="cdo-field-hint">
										<?php
										/* translators: %s: ID of the service account key. */
										echo esc_html( sprintf( __( 'Key ID: %s', 'fast-index' ), '' !== $fast_index_account['key_id'] ? $fast_index_account['key_id'] : '-' ) );
										?>
									</span>
								</th>
								<td>
									<span class="<?php echo esc_attr( null === $fast_index_account['status'] ? 'cdo-badge' : 'cdo-badge ' . Fast_Index_Admin::status_tone( $fast_index_account['status'] ) ); ?>"><bdi dir="auto"><?php echo esc_html( null === $fast_index_account['status'] ? __( 'Not used yet', 'fast-index' ) : Fast_Index_Admin::status_label( $fast_index_account['status'] ) ); ?></bdi></span>
									<?php if ( ! $fast_index_view['several_keys'] && count( $fast_index_view['accounts'] ) > 1 ) : ?>
										<p class="description fi-key-use"><?php echo esc_html( $fast_index_account['in_use'] ? __( 'Used for sending', 'fast-index' ) : __( 'Kept, not used by this version', 'fast-index' ) ); ?></p>
									<?php elseif ( $fast_index_view['several_keys'] && count( $fast_index_view['accounts'] ) > 1 ) : ?>
										<p class="description fi-key-use"><?php echo esc_html( $fast_index_account['main'] ? __( 'Main key', 'fast-index' ) : __( 'Backup key: used only when Google rejects the key before it', 'fast-index' ) ); ?></p>
									<?php endif; ?>
									<?php if ( $fast_index_account['paused_until'] > 0 ) : ?>
										<p class="description fi-key-paused"><?php echo esc_html( Fast_Index_Admin::pause_label( $fast_index_account['paused_until'] ) ); ?></p>
									<?php endif; ?>
									<form method="post" class="fast-index-inline-form cdo-inline-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="<?php echo esc_attr( Fast_Index_Admin_Actions::DELETE ); ?>" />
										<input type="hidden" name="fast_index_account" value="<?php echo esc_attr( $fast_index_account['id'] ); ?>" />
										<input type="hidden" name="<?php echo esc_attr( Fast_Index_Admin_Actions::NONCE_FIELD ); ?>" value="<?php echo esc_attr( wp_create_nonce( Fast_Index_Admin_Actions::DELETE ) ); ?>" />
										<button type="submit" class="button-link button-link-delete" data-cdo-confirm-danger data-cdo-confirm-title="<?php esc_attr_e( 'Delete this key?', 'fast-index' ); ?>" data-cdo-confirm="<?php esc_attr_e( 'Fast Index stops using this key. If it is your only key, nothing is sent to Google until you upload another one.', 'fast-index' ); ?>" data-cdo-confirm-ok="<?php esc_attr_e( 'Delete key', 'fast-index' ); ?>" data-cdo-confirm-cancel="<?php esc_attr_e( 'Keep it', 'fast-index' ); ?>"><?php esc_html_e( 'Delete', 'fast-index' ); ?></button>
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
					<table class="form-table cdo-form-table" role="presentation">
						<tbody>
						<tr>
							<th scope="row"><label for="fast-index-key-files"><?php esc_html_e( 'JSON Key File', 'fast-index' ); ?></label><?php Fast_Index_Admin::render_whatis( 'service_account' ); ?></th>
							<td>
								<input id="fast-index-key-files" class="jsonFileUpload" type="file" accept=".json,application/json" name="fast_index_key_files[]" <?php echo $fast_index_view['several_keys'] ? 'multiple' : ''; ?> />
								<p class="description"><?php esc_html_e( 'The key file is read and stored in the database; it is not saved in the uploads folder. Only the email address and the key ID are shown here.', 'fast-index' ); ?></p>
								<?php if ( ! $fast_index_view['several_keys'] ) : ?>
									<p class="description"><?php esc_html_e( 'A new key replaces the key that is used for sending.', 'fast-index' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						</tbody>
					</table>
					<p class="cdo-actions">
						<input class="button" type="submit" value="<?php esc_attr_e( 'Upload key', 'fast-index' ); ?>" />
					</p>
				</form>
			</section>

		</div>

		<div class="cdo-aside cdo-stack">

			<section class="cdo-card" aria-labelledby="fi-help-card-title">
				<div class="cdo-card__header">
					<h2 id="fi-help-card-title" class="cdo-card__title"><?php esc_html_e( 'Help & FAQ', 'fast-index' ); ?></h2>
				</div>
				<p><?php esc_html_e( 'Setting up for the first time, or something does not work? Step-by-step instructions, an explanation of every setting, solutions to common problems and answers to frequently asked questions are in Help & FAQ.', 'fast-index' ); ?></p>
				<ul>
					<?php foreach ( Fast_Index_Help_Content::sections() as $fast_index_section => $fast_index_section_title ) : ?>
						<li><a href="<?php echo esc_url( $fast_index_help_url . '#fi-help-' . $fast_index_section ); ?>"><?php echo esc_html( $fast_index_section_title ); ?></a></li>
					<?php endforeach; ?>
				</ul>
				<p class="cdo-actions">
					<a class="button" href="<?php echo esc_url( $fast_index_help_url ); ?>"><?php esc_html_e( 'Open Help & FAQ', 'fast-index' ); ?></a>
				</p>
				<p class="cdo-description"><?php esc_html_e( 'Fast Index sends your addresses to the Google Indexing API. A 200 answer means Google accepted the notification; Google does not promise to index the page.', 'fast-index' ); ?></p>
				<p class="cdo-description"><a target="_blank" rel="noopener noreferrer" href="https://wordpress.org/support/plugin/fast-index/reviews/"><?php esc_html_e( 'Rate Fast Index on WordPress.org', 'fast-index' ); ?></a></p>
			</section>


		</div>

		<?php if ( null !== $fast_index_view['premium_card'] ) : ?>
			<section class="cdo-full cdo-card cdo-premium fi-premium-card" aria-labelledby="fi-premium-title">
				<div class="cdo-card__header">
					<h2 id="fi-premium-title" class="cdo-card__title"><?php echo esc_html_x( 'Premium', 'Heading of the box about the premium plan', 'fast-index' ); ?></h2>
					<p class="cdo-card__desc"><?php esc_html_e( 'The premium plan is a separate package. What each version does:', 'fast-index' ); ?></p>
				</div>
				<table class="fi-compare cdo-compare">
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
										<span class="fi-yes cdo-yes" aria-hidden="true">&#10003;</span><span class="screen-reader-text"><?php esc_html_e( 'Included', 'fast-index' ); ?></span>
									<?php elseif ( false === $fast_index_cell ) : ?>
										<span class="fi-no cdo-no" aria-hidden="true">&ndash;</span><span class="screen-reader-text"><?php esc_html_e( 'Not included', 'fast-index' ); ?></span>
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
					<p class="cdo-actions"><a class="button" href="<?php echo esc_url( $fast_index_view['premium_card']['url'] ); ?>"><?php esc_html_e( 'Upgrade to Premium', 'fast-index' ); ?></a></p>
				<?php endif; ?>
			</section>
		<?php endif; ?>

	</div>

</div>
