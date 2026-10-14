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
							</td>
						</tr>

						<tr>
							<td scope="row">
								<label for="fast-index-delete-data"><b><?php esc_html_e( 'Delete data on uninstall', 'fast-index' ); ?></b></label> <br>
								<small><?php esc_html_e( 'Delete all plugin data (settings, keys and log) when the plugin is deleted. This cannot be undone.', 'fast-index' ); ?></small>
							</td>
							<td>
								<input id="fast-index-delete-data" type="checkbox" name="fast_index_options[delete_data]" value="1" <?php checked( $fast_index_settings['delete_data'] ); ?> />
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

			<h1 class="title"><?php esc_html_e( 'Guideline', 'fast-index' ); ?></h1>

			<div>

				<h3>1- <?php esc_html_e( 'What means 200, 429, 403 and 401 codes?', 'fast-index' ); ?></h3>
				<p>
					<?php
					$fast_index_codes = array(
						200 => __( "It's means working and ready", 'fast-index' ),
						429 => __( "Too many requests are thrown and when it's 24 hours it starts again", 'fast-index' ),
						401 => __( "It means that the service account you have installed is not authorized or authorized as 'owner' in your webmaster tools account", 'fast-index' ),
						403 => __( "It means the request page is forbidden. Check your site url. Htpps or Http it's important.", 'fast-index' ),
					);

					foreach ( $fast_index_codes as $fast_index_code => $fast_index_text ) :
						?>
						<b>
						<?php
						/* translators: %d: HTTP status code. */
						echo esc_html( sprintf( __( 'Code %d', 'fast-index' ), $fast_index_code ) );
						?>
						</b>; <?php echo esc_html( $fast_index_text ); ?><br />
					<?php endforeach; ?>
				</p>
				<p>
					<b><?php esc_html_e( "Note : If you see 200 or 429 don't do anything. If you see 401 or 4xx codes, check your webmaster tools owners", 'fast-index' ); ?></b>
				</p>
				<hr />
				<br />
				<h3>2- <?php esc_html_e( 'Settings', 'fast-index' ); ?></h3>
				<p>
					<b><?php esc_html_e( 'Status', 'fast-index' ); ?></b>: <?php esc_html_e( "If you don't use set as passive", 'fast-index' ); ?>
					<br />
					<b><?php esc_html_e( 'Post Types', 'fast-index' ); ?></b>: <?php esc_html_e( 'Define the when you make post action which one post types will send to google. If you add new post type or added from plugin it will be shown in here', 'fast-index' ); ?>
					<br />
					<b><?php esc_html_e( 'Daily Old Content Post', 'fast-index' ); ?></b>: <?php esc_html_e( 'If you wanna sent to google your old posts type the your daily limit. Every service account has daily 200 limit and you have to split your limits daily new post and old posts', 'fast-index' ); ?>
					<br>
					<b><?php esc_html_e( 'Post Status', 'fast-index' ); ?></b>: <?php esc_html_e( "It's means which post status trigger the this plugin", 'fast-index' ); ?>
				</p>
				<hr />
				<br />
				<h3>3- <?php esc_html_e( 'Is it legal?', 'fast-index' ); ?></h3>
				<p><?php esc_html_e( "Totally is legal. It's google service and working with google API. If you upload too much service account it's can be defined a spam. Just watch out for this", 'fast-index' ); ?></p>
				<hr />
				<br />
				<h3>4- <?php esc_html_e( 'How work WordPress Cron Job ( Daily Old Content Post )?', 'fast-index' ); ?></h3>
				<p><?php esc_html_e( 'The task list is triggered when someone logs into the site at or after the specified hours. These tasks will never be triggered if no one accesses your site. If no one visits your site during the day, log in to your site for once and the task list will be triggered automatically', 'fast-index' ); ?></p>
				<hr />
				<br />
				<h3>5- <?php esc_html_e( 'Mass Service Account creating and upload', 'fast-index' ); ?></h3>
				<p>
					<b><?php esc_html_e( 'Note', 'fast-index' ); ?></b>: <?php esc_html_e( '1 Google account can create minimum 12 google cloud projects. Every google cloud project can enable Indexing API Service and every services has 200 daily limit. It\'s means you can send 2400 url to google. If you do same steps with your another google account you will get more 2400 limit and you 4800 url to google daily', 'fast-index' ); ?>
				</p>
				<p>
					<?php
					$fast_index_steps = array(
						1 => __( 'Go Link', 'fast-index' ),
						2 => __( 'Create Project and Select', 'fast-index' ),
						3 => __( 'Create Service Account and make authorized you created email on service account', 'fast-index' ),
						4 => __( 'Add as owner on your webmaster tools', 'fast-index' ),
						5 => __( 'Go your wordpress admin dashboard and open Fast Index settings page and upload your service account JSON', 'fast-index' ),
					);

					foreach ( $fast_index_steps as $fast_index_step => $fast_index_text ) :
						?>
						<b>
						<?php
						/* translators: %d: step number. */
						echo esc_html( sprintf( __( 'Step %d', 'fast-index' ), $fast_index_step ) );
						?>
						</b>: <?php echo esc_html( $fast_index_text ); ?>
						<?php if ( 1 === $fast_index_step ) : ?>
							<a target="_blank" rel="noopener noreferrer" href="https://console.cloud.google.com/">https://console.cloud.google.com/</a>
						<?php endif; ?>
						<br>
					<?php endforeach; ?>
				</p>
				<p><?php esc_html_e( 'Watch Video', 'fast-index' ); ?> : <a target="_blank" rel="noopener noreferrer" href="https://youtu.be/RsJA66b5884">https://youtu.be/RsJA66b5884</a></p>
				<p>
					<iframe style="padding: 10px; border: 1px dotted #666; border-radius: 3px;" width="560" height="315" src="https://www.youtube.com/embed/RsJA66b5884" title="<?php esc_attr_e( 'YouTube video player', 'fast-index' ); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
				</p>

			</div>

		</div>

		<div class="fiClear"></div>

	</div>

</div>
