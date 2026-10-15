<?php
/**
 * Header band of every Fast Index screen: the CultDevOps family mark, the plugin name, the plan and the screen title
 * (the only <h1> of the page). WordPress moves its notices below the .wp-header-end line that follows.
 *
 * Expects $fast_index_band from Fast_Index_Admin::render_band(): title and lead (plain text, lead may be empty),
 * help_url (link to Help & FAQ, empty on Help & FAQ itself).
 *
 * @package FastIndex
 */

defined( 'ABSPATH' ) || exit;
?>
<header class="cdo-band">
	<p class="cdo-band__brand">
		<svg class="cdo-mark" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><rect class="cdo-mark__tile" width="24" height="24" rx="6"/><rect class="cdo-mark__bar cdo-mark__bar--fast-index" x="5" y="6" width="14" height="3" rx="1.5"/><rect class="cdo-mark__bar cdo-mark__bar--llms" x="5" y="10.5" width="10" height="3" rx="1.5"/><rect class="cdo-mark__bar cdo-mark__bar--xml-sitemap" x="5" y="15" width="6" height="3" rx="1.5"/></svg>
		<span class="cdo-band__family">CultDevOps</span>
		<span class="cdo-band__sep" aria-hidden="true">/</span>
		<span class="cdo-band__product"><?php echo esc_html__( 'Fast Index', 'fast-index' ); ?></span>
		<span class="cdo-band__meta">
			<?php if ( '' !== $fast_index_band['help_url'] ) : ?>
				<a class="cdo-band__help" href="<?php echo esc_url( $fast_index_band['help_url'] ); ?>"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span><?php echo esc_html__( 'Help & FAQ', 'fast-index' ); ?></a>
			<?php endif; ?>
			<?php if ( Fast_Index_Plugin::premium_loaded() ) : ?>
				<span class="cdo-band__plan cdo-band__plan--premium"><?php echo esc_html_x( 'Premium', 'Plan name in the page header', 'fast-index' ); ?></span>
			<?php else : ?>
				<span class="cdo-band__plan"><?php echo esc_html_x( 'Free', 'Plan name in the page header', 'fast-index' ); ?></span>
			<?php endif; ?>
		</span>
	</p>
	<h1 class="cdo-band__title"><?php echo esc_html( $fast_index_band['title'] ); ?></h1>
	<?php if ( '' !== $fast_index_band['lead'] ) : ?>
		<p class="cdo-band__lead"><?php echo esc_html( $fast_index_band['lead'] ); ?></p>
	<?php endif; ?>
	<span class="cdo-band__stripe" aria-hidden="true"><span class="cdo-band__stripe-fast-index"></span><span class="cdo-band__stripe-llms"></span><span class="cdo-band__stripe-xml-sitemap"></span></span>
</header>
<hr class="wp-header-end">
