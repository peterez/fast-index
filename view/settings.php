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
$fast_index_premium  = $fast_index_view['premium'];
$fast_index_help_url = admin_url( 'admin.php?page=' . Fast_Index_Help::SLUG );
$fast_index_events   = array(
	'publish' => __( 'Publish', 'fast-index' ),
	'edit'    => __( 'Edit', 'fast-index' ),
	'trash'   => __( 'Trash', 'fast-index' ),
);
?>
<div id="fi" class="content-area">

	<?php foreach ( $fast_index_view['messages'] as $fast_index_message ) : ?>
		<div class="<?php echo esc_attr( 'notice notice-' . $fast_index_message['type'] . ' is-dismissible' ); ?>">
			<p><?php echo esc_html( $fast_index_message['message'] ); ?></p>
		</div>
	<?php endforeach; ?>

	<div class="pc">

		<div class="pw50">

			<h1 class="title"><?php esc_html_e( 'Settings', 'fast-index' ); ?></h1>

			<div class="form">

				<form method="post" class="settingsForm" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( Fast_Index_Admin_Actions::SAVE ); ?>" />
					<?php wp_nonce_field( Fast_Index_Admin_Actions::SAVE, Fast_Index_Admin_Actions::NONCE_FIELD ); ?>

					<table class="form-table" role="presentation">
						<tbody>

						<tr>
							<td scope="row">
								<label for="fast-index-status"><b><?php esc_html_e( 'Status', 'fast-index' ); ?></b></label> <br>
								<small><?php esc_html_e( 'Important choice for the plugin work', 'fast-index' ); ?></small>
							</td>
							<td>
								<select id="fast-index-status" name="fast_index_options[status]">
									<option value="<?php echo esc_attr( Fast_Index_Settings::STATUS_ACTIVE ); ?>" <?php selected( $fast_index_settings['status'], Fast_Index_Settings::STATUS_ACTIVE ); ?>><?php esc_html_e( 'Active', 'fast-index' ); ?></option>
									<option value="<?php echo esc_attr( Fast_Index_Settings::STATUS_PASSIVE ); ?>" <?php selected( $fast_index_settings['status'], Fast_Index_Settings::STATUS_PASSIVE ); ?>><?php esc_html_e( 'Passive', 'fast-index' ); ?></option>
								</select>
								<p class="description"><?php echo esc_html( Fast_Index_Help_Content::field_description( 'status' ) ); ?></p>
							</td>
						</tr>

						<tr>
							<td scope="row">
								<b><?php esc_html_e( 'Post Types', 'fast-index' ); ?></b> <br>
								<small><?php esc_html_e( 'Select minimum one option', 'fast-index' ); ?></small>
							</td>
							<td>
								<?php
								foreach ( $fast_index_view['post_types'] as $fast_index_name => $fast_index_label ) :
									$fast_index_locked = ! $fast_index_premium && 'post' !== $fast_index_name;
									?>
									<label class="<?php echo esc_attr( $fast_index_locked ? 'fast-index-choice licenceAlert' : 'fast-index-choice' ); ?>">
										<input type="checkbox" name="<?php echo esc_attr( 'fast_index_options[post_type][' . $fast_index_name . ']' ); ?>" value="1" <?php checked( isset( $fast_index_settings['post_type'][ $fast_index_name ] ) ); ?> <?php echo $fast_index_locked ? 'readonly' : ''; ?> />
										<?php echo esc_html( $fast_index_label ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description"><?php echo esc_html( Fast_Index_Help_Content::field_description( 'post_type' ) ); ?></p>
							</td>
						</tr>

						<tr>
							<td scope="row">
								<label for="fast-index-old-posts"><b><?php esc_html_e( 'Daily Old Content Post?', 'fast-index' ); ?></b></label> <br>
								<small><?php esc_html_e( 'How many old contents should be sent per day?', 'fast-index' ); ?></small>
							</td>
							<td>
								<?php if ( $fast_index_premium ) : ?>
									<input id="fast-index-old-posts" class="regular-text" type="number" min="0" max="<?php echo esc_attr( Fast_Index_Settings::MAX_OLD_POSTS ); ?>" name="fast_index_options[old_post_number]" value="<?php echo esc_attr( $fast_index_settings['old_post_number'] ); ?>" />
								<?php else : ?>
									<input id="fast-index-old-posts" class="regular-text licenceAlert" type="text" value="0" readonly disabled />
								<?php endif; ?>
								<p class="description"><?php echo esc_html( Fast_Index_Help_Content::field_description( 'old_post_number' ) ); ?></p>
							</td>
						</tr>

						<tr>
							<td scope="row">
								<b><?php esc_html_e( 'Post Status', 'fast-index' ); ?></b> <br>
								<small><?php esc_html_e( 'Which status happen should content be sent?', 'fast-index' ); ?></small>
							</td>
							<td>
								<?php
								foreach ( $fast_index_events as $fast_index_event => $fast_index_label ) :
									$fast_index_locked = ! $fast_index_premium && 'publish' !== $fast_index_event;
									?>
									<label class="<?php echo esc_attr( $fast_index_locked ? 'fast-index-choice licenceAlert' : 'fast-index-choice' ); ?>">
										<input type="checkbox" name="<?php echo esc_attr( 'fast_index_options[post_status][' . $fast_index_event . ']' ); ?>" value="1" <?php checked( isset( $fast_index_settings['post_status'][ $fast_index_event ] ) ); ?> <?php echo $fast_index_locked ? 'readonly' : ''; ?> />
										<?php echo esc_html( $fast_index_label ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description"><?php echo esc_html( Fast_Index_Help_Content::field_description( 'post_status' ) ); ?></p>
							</td>
						</tr>

						<tr>
							<td scope="row" colspan="2">
								<b><?php esc_html_e( 'Exclude Categories', 'fast-index' ); ?></b> <br>
								<small><?php esc_html_e( 'Select which you want to exclude', 'fast-index' ); ?></small>
							</td>
						</tr>

						<tr>
							<td colspan="2">
								<?php foreach ( $fast_index_view['categories'] as $fast_index_category ) : ?>
									<label class="<?php echo esc_attr( $fast_index_premium ? 'fast-index-choice' : 'fast-index-choice licenceAlert' ); ?>">
										<input type="checkbox" name="<?php echo esc_attr( 'fast_index_options[exclude_category][' . $fast_index_category->term_id . ']' ); ?>" value="<?php echo esc_attr( $fast_index_category->term_id ); ?>" <?php checked( isset( $fast_index_settings['exclude_category'][ (int) $fast_index_category->term_id ] ) ); ?> <?php echo $fast_index_premium ? '' : 'readonly'; ?> />
										<?php echo esc_html( $fast_index_category->name ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description"><?php echo esc_html( Fast_Index_Help_Content::field_description( 'exclude_category' ) ); ?></p>
							</td>
						</tr>

						<tr>
							<td scope="row">
								<label for="fast-index-delete-data"><b><?php esc_html_e( 'Delete data on uninstall', 'fast-index' ); ?></b></label> <br>
								<small><?php esc_html_e( 'Delete all plugin data (settings, keys and log) when the plugin is deleted. This cannot be undone.', 'fast-index' ); ?></small>
							</td>
							<td>
								<input id="fast-index-delete-data" type="checkbox" name="fast_index_options[delete_data]" value="1" <?php checked( $fast_index_settings['delete_data'] ); ?> />
								<p class="description"><?php echo esc_html( Fast_Index_Help_Content::field_description( 'delete_data' ) ); ?></p>
							</td>
						</tr>

						<tr>
							<td colspan="2">
								<a target="_blank" rel="noopener noreferrer" href="https://wordpress.org/support/plugin/fast-index/reviews/"><?php esc_html_e( 'Rate Us on WordPress', 'fast-index' ); ?></a>
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

				<h1 class="title"><?php esc_html_e( 'Upload Google Service Account Json', 'fast-index' ); ?></h1>

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
									<?php echo esc_html( null === $fast_index_account['status'] ? __( 'Not used yet', 'fast-index' ) : Fast_Index_Admin::status_label( $fast_index_account['status'] ) ); ?>
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
							<td scope="row"><label for="fast-index-key-files"><b><?php esc_html_e( 'Choose Json File/s', 'fast-index' ); ?></b></label></td>
							<td>
								<input id="fast-index-key-files" class="jsonFileUpload" type="file" accept=".json,application/json" name="fast_index_key_files[]" <?php echo $fast_index_premium ? 'multiple' : ''; ?> />
								<p class="description"><?php esc_html_e( 'The key file is read and stored in the database; it is not saved in the uploads folder. Only the client email and the key ID are shown here.', 'fast-index' ); ?></p>
							</td>
						</tr>

						<?php if ( ! $fast_index_premium ) : ?>
							<tr>
								<td colspan="2">
									<div class="licenceAlert laBg">
										<?php esc_html_e( 'If you wanna upload multiple and more service account', 'fast-index' ); ?>
										<br>
										<b><?php esc_html_e( 'Please upgrade to premium', 'fast-index' ); ?></b>
									</div>
								</td>
							</tr>
						<?php endif; ?>

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

		</div>

		<div class="fiClear"></div>

	</div>

</div>
