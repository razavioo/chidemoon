<?php
/**
 * Persian search form. One field composition serves the Blocksy header
 * modal, the mobile off-canvas menu, the search-archive refine box, and the
 * 404 page. Blocksy's default form ships English placeholders and an
 * unlabelled submit control.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Blocksy renders the header modal through get_search_form() with its own
// argument set; inline placements (404, refine box) pass none. The modal is
// the only context that earns the hint row and section shortcuts.
$is_modal  = isset( $args['search_placeholder'] );
$unique_id = wp_unique_id( 'chidemoon-search-field-' );

// An empty query must never reach the archive as "results": the submit
// control starts disabled and assets/js/search-form.js keeps it in sync
// with the field, so only a real phrase can be submitted.
$has_query = '' !== trim( (string) get_query_var( 's' ) );
?>
<form role="search" method="get" class="chidemoon-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="chidemoon-sr-only" for="<?php echo esc_attr( $unique_id ); ?>"><?php esc_html_e( 'جست‌وجو در چیدمون', 'chidemoon-blocksy-child' ); ?></label>
	<div class="chidemoon-search-form__field">
		<svg class="chidemoon-search-form__icon" aria-hidden="true" width="17" height="17" viewBox="0 0 15 15" focusable="false"><path d="M14.8,13.7L12,11c0.9-1.2,1.5-2.6,1.5-4.2c0-3.7-3-6.8-6.8-6.8S0,3,0,6.8s3,6.8,6.8,6.8c1.6,0,3.1-0.6,4.2-1.5l2.8,2.8c0.1,0.1,0.3,0.2,0.5,0.2s0.4-0.1,0.5-0.2C15.1,14.5,15.1,14,14.8,13.7z M1.5,6.8c0-2.9,2.4-5.2,5.2-5.2S12,3.9,12,6.8S9.6,12,6.8,12S1.5,9.6,1.5,6.8z"/></svg>
		<input
			id="<?php echo esc_attr( $unique_id ); ?>"
			type="search"
			name="s"
			placeholder="<?php esc_attr_e( 'مثلاً: چراغ مطالعه', 'chidemoon-blocksy-child' ); ?>"
			value="<?php echo esc_attr( get_search_query() ); ?>"
		>
		<button type="submit" <?php disabled( ! $has_query ); ?>><?php esc_html_e( 'جست‌وجو', 'chidemoon-blocksy-child' ); ?></button>
	</div>
	<?php if ( $is_modal ) : ?>
		<p class="chidemoon-search-form__hint">
			<span><kbd>Enter</kbd> <?php esc_html_e( 'برای جست‌وجو', 'chidemoon-blocksy-child' ); ?></span>
			<span><kbd>Esc</kbd> <?php esc_html_e( 'برای بستن', 'chidemoon-blocksy-child' ); ?></span>
		</p>
		<?php chidemoon_search_quick_links(); ?>
	<?php endif; ?>
</form>
