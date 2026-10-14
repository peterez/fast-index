<?php
/**
 * Premium rows of the settings form: daily limit for old posts and excluded categories.
 *
 * Expects $fast_index_settings and $fast_index_categories from Fast_Index_Premium_Features::render_rows().
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;
?>
<tr>
	<td scope="row">
		<label for="fast-index-old-posts"><b><?php esc_html_e( 'Daily Limit for Old Posts', 'fast-index' ); ?></b></label> <br>
		<small><?php esc_html_e( 'Number of old posts to send per day.', 'fast-index' ); ?></small>
	</td>
	<td>
		<input id="fast-index-old-posts" class="regular-text" type="number" min="0" max="<?php echo esc_attr( Fast_Index_Settings::MAX_OLD_POSTS ); ?>" name="fast_index_options[old_post_number]" value="<?php echo esc_attr( $fast_index_settings['old_post_number'] ); ?>" />
		<?php foreach ( Fast_Index_Help_Content::field_notes( 'old_post_number' ) as $fast_index_note ) : ?>
			<p class="description"><?php echo esc_html( $fast_index_note ); ?></p>
		<?php endforeach; ?>
	</td>
</tr>

<tr>
	<td scope="row" colspan="2">
		<b><?php esc_html_e( 'Exclude Categories', 'fast-index' ); ?></b> <br>
		<small><?php esc_html_e( 'Select the categories to exclude.', 'fast-index' ); ?></small>
	</td>
</tr>

<tr>
	<td colspan="2">
		<?php foreach ( $fast_index_categories as $fast_index_category ) : ?>
			<label class="fast-index-choice">
				<input type="checkbox" name="<?php echo esc_attr( 'fast_index_options[exclude_category][' . $fast_index_category->term_id . ']' ); ?>" value="<?php echo esc_attr( $fast_index_category->term_id ); ?>" <?php checked( isset( $fast_index_settings['exclude_category'][ (int) $fast_index_category->term_id ] ) ); ?> />
				<?php echo esc_html( $fast_index_category->name ); ?>
			</label>
		<?php endforeach; ?>
		<?php foreach ( Fast_Index_Help_Content::field_notes( 'exclude_category' ) as $fast_index_note ) : ?>
			<p class="description"><?php echo esc_html( $fast_index_note ); ?></p>
		<?php endforeach; ?>
	</td>
</tr>
