<?php
/**
 * Persian search form. Blocksy's default form ships English placeholders and
 * an unlabelled submit control.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form role="search" method="get" class="chidemoon-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="chidemoon-sr-only" for="chidemoon-search-field"><?php esc_html_e( 'جست‌وجو در چیدمون', 'chidemoon-blocksy-child' ); ?></label>
	<input
		id="chidemoon-search-field"
		type="search"
		name="s"
		placeholder="<?php esc_attr_e( 'مثلاً: چراغ مطالعه', 'chidemoon-blocksy-child' ); ?>"
		value="<?php echo esc_attr( get_search_query() ); ?>"
	>
	<button type="submit"><?php esc_html_e( 'جست‌وجو', 'chidemoon-blocksy-child' ); ?></button>
</form>
