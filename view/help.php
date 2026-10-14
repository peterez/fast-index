<?php
/**
 * Help & FAQ screen.
 *
 * Expects $fast_index_view from Fast_Index_Help::render().
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;

$fast_index_allowed = Fast_Index_Help_Content::allowed_html();
?>
<div class="wrap fast-index-help">

	<h1><?php esc_html_e( 'Help & FAQ', 'fast-index' ); ?></h1>

	<p class="fi-help-lead"><?php esc_html_e( 'Everything you need to set up Fast Index and to solve the most common problems. Read it before you write to support: the answer is most often here.', 'fast-index' ); ?></p>

	<nav class="fi-help-nav" aria-label="<?php esc_attr_e( 'Help sections', 'fast-index' ); ?>">
		<ul>
			<?php foreach ( $fast_index_view['sections'] as $fast_index_key => $fast_index_title ) : ?>
				<li><a href="<?php echo esc_attr( '#fi-help-' . $fast_index_key ); ?>"><?php echo esc_html( $fast_index_title ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</nav>

	<?php /* Getting started. */ ?>
	<section id="fi-help-getting-started" class="card fi-help-section" aria-labelledby="fi-help-getting-started-title">
		<h2 id="fi-help-getting-started-title"><?php echo esc_html( $fast_index_view['sections']['getting-started'] ); ?></h2>

		<p><?php echo wp_kses( Fast_Index_Help_Content::getting_started_intro(), $fast_index_allowed ); ?></p>

		<div class="notice notice-info inline"><p><?php echo wp_kses( Fast_Index_Help_Content::getting_started_notice(), $fast_index_allowed ); ?></p></div>

		<ol class="fi-help-steps">
			<?php foreach ( $fast_index_view['steps'] as $fast_index_step ) : ?>
				<li>
					<h3><?php echo esc_html( $fast_index_step['title'] ); ?></h3>
					<p><?php echo wp_kses( $fast_index_step['text'], $fast_index_allowed ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>

		<p><?php echo wp_kses( Fast_Index_Help_Content::getting_started_video(), $fast_index_allowed ); ?></p>
	</section>

	<?php /* Settings explained. */ ?>
	<section id="fi-help-settings" class="card fi-help-section" aria-labelledby="fi-help-settings-title">
		<h2 id="fi-help-settings-title"><?php echo esc_html( $fast_index_view['sections']['settings'] ); ?></h2>

		<div class="fi-help-table-wrap">
			<table class="widefat striped fi-help-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Setting', 'fast-index' ); ?></th>
						<th scope="col"><?php esc_html_e( 'What it does', 'fast-index' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Default', 'fast-index' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Change it when', 'fast-index' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $fast_index_view['settings'] as $fast_index_setting ) : ?>
						<tr id="<?php echo esc_attr( 'fi-help-setting-' . $fast_index_setting['id'] ); ?>">
							<th scope="row" data-label="<?php esc_attr_e( 'Setting', 'fast-index' ); ?>"><?php echo esc_html( $fast_index_setting['name'] ); ?></th>
							<td data-label="<?php esc_attr_e( 'What it does', 'fast-index' ); ?>">
								<p class="fi-help-what"><?php echo wp_kses( $fast_index_setting['what'], $fast_index_allowed ); ?></p>
								<?php if ( ! empty( $fast_index_setting['note'] ) ) : ?>
									<?php /* A paragraph of its own, not a sentence joined with a space (Japanese writes none). */ ?>
									<p class="fi-help-what"><?php echo esc_html( $fast_index_setting['note'] ); ?></p>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Default', 'fast-index' ); ?>"><?php echo esc_html( $fast_index_setting['default'] ); ?></td>
							<td data-label="<?php esc_attr_e( 'Change it when', 'fast-index' ); ?>"><?php echo wp_kses( $fast_index_setting['when'], $fast_index_allowed ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>

	<?php /* Troubleshooting. */ ?>
	<section id="fi-help-troubleshooting" class="card fi-help-section" aria-labelledby="fi-help-troubleshooting-title">
		<h2 id="fi-help-troubleshooting-title"><?php echo esc_html( $fast_index_view['sections']['troubleshooting'] ); ?></h2>

		<p><?php esc_html_e( 'Find what you see, then read why it happens and what to do.', 'fast-index' ); ?></p>

		<?php foreach ( $fast_index_view['problems'] as $fast_index_problem ) : ?>
			<details id="<?php echo esc_attr( 'fi-help-problem-' . $fast_index_problem['id'] ); ?>" class="fi-help-item">
				<summary><?php echo esc_html( $fast_index_problem['symptom'] ); ?></summary>
				<div class="fi-help-item__body">
					<p><strong><?php esc_html_e( 'Why it happens:', 'fast-index' ); ?></strong> <?php echo wp_kses( $fast_index_problem['cause'], $fast_index_allowed ); ?></p>
					<p><strong><?php esc_html_e( 'What to do:', 'fast-index' ); ?></strong> <?php echo wp_kses( $fast_index_problem['fix'], $fast_index_allowed ); ?></p>
				</div>
			</details>
		<?php endforeach; ?>
	</section>

	<?php /* FAQ. */ ?>
	<section id="fi-help-faq" class="card fi-help-section" aria-labelledby="fi-help-faq-title">
		<h2 id="fi-help-faq-title"><?php echo esc_html( $fast_index_view['sections']['faq'] ); ?></h2>

		<?php foreach ( $fast_index_view['faq'] as $fast_index_item ) : ?>
			<details id="<?php echo esc_attr( 'fi-help-faq-' . $fast_index_item['id'] ); ?>" class="fi-help-item">
				<summary><?php echo esc_html( $fast_index_item['question'] ); ?></summary>
				<div class="fi-help-item__body">
					<p><?php echo wp_kses( $fast_index_item['answer'], $fast_index_allowed ); ?></p>
				</div>
			</details>
		<?php endforeach; ?>
	</section>

	<?php /* Still need help? */ ?>
	<section id="fi-help-support" class="card fi-help-section" aria-labelledby="fi-help-support-title">
		<h2 id="fi-help-support-title"><?php echo esc_html( $fast_index_view['sections']['support'] ); ?></h2>

		<p><?php esc_html_e( 'Before you write to support, go through this list:', 'fast-index' ); ?></p>
		<ol>
			<?php foreach ( $fast_index_view['checklist'] as $fast_index_check ) : ?>
				<li><?php echo wp_kses( $fast_index_check, $fast_index_allowed ); ?></li>
			<?php endforeach; ?>
		</ol>

		<h3 id="fi-help-system-info-title"><?php esc_html_e( 'System info', 'fast-index' ); ?></h3>
		<p id="fi-help-system-info-note"><?php esc_html_e( 'Read-only facts about your site that help us answer. They contain no keys and no email addresses.', 'fast-index' ); ?></p>
		<?php /* Label and value are separate isolated elements, so each side keeps its own direction on right-to-left screens. */ ?>
		<dl class="fi-help-sysinfo" aria-describedby="fi-help-system-info-note">
			<?php foreach ( $fast_index_view['system_rows'] as $fast_index_row ) : ?>
				<div class="fi-help-sysinfo__row">
					<dt><bdi dir="auto"><?php echo esc_html( $fast_index_row['label'] ); ?></bdi></dt>
					<dd><bdi dir="auto"><?php echo esc_html( $fast_index_row['value'] ); ?></bdi></dd>
				</div>
			<?php endforeach; ?>
		</dl>
		<?php /* The same facts as plain text, one line each: the source of the copy button. Hidden from view and from assistive technology until the copy button has to select it. */ ?>
		<textarea id="fi-help-system-info" class="fi-help-sysinfo-source" rows="<?php echo esc_attr( (string) ( substr_count( $fast_index_view['system_info'], "\n" ) + 1 ) ); ?>" aria-labelledby="fi-help-system-info-title" readonly="readonly" tabindex="-1" aria-hidden="true"><?php echo esc_textarea( $fast_index_view['system_info'] ); ?></textarea>
		<p>
			<button type="button" class="button" data-fast-index-copy="fi-help-system-info"><?php esc_html_e( 'Copy system info', 'fast-index' ); ?></button>
			<span class="fi-help-copy-status" role="status" aria-live="polite"></span>
		</p>

		<p>
			<?php echo wp_kses( Fast_Index_Help_Content::ext( $fast_index_view['support_url'], __( 'Open the Fast Index support forum on WordPress.org', 'fast-index' ) ), $fast_index_allowed ); ?>
		</p>
	</section>

</div>
