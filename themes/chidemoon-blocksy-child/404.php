<?php
/**
 * Persian 404. The default Blocksy fallback renders English copy and an
 * unthemed search form, so this template keeps the editorial voice consistent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="primary" class="site-main chidemoon-error-404">
	<section class="chidemoon-error-404__panel chidemoon-section-shell">
		<p class="chidemoon-error-404__code" aria-hidden="true"><?php echo esc_html( chidemoon_fa_digits( '404' ) ); ?></p>
		<h1><?php esc_html_e( 'این صفحه پیدا نشد', 'chidemoon-blocksy-child' ); ?></h1>
		<p class="chidemoon-error-404__description">
			<?php esc_html_e( 'نشانی‌ای که باز کرده‌اید وجود ندارد یا جابه‌جا شده است. از جست‌وجو استفاده کنید یا از یکی از بخش‌های اصلی ادامه دهید.', 'chidemoon-blocksy-child' ); ?>
		</p>
		<div class="chidemoon-error-404__search">
			<?php
			get_search_form();
			?>
		</div>
		<nav class="chidemoon-error-404__links" aria-label="<?php esc_attr_e( 'بخش‌های اصلی', 'chidemoon-blocksy-child' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'خانه', 'chidemoon-blocksy-child' ); ?></a>
			<a href="<?php echo esc_url( chidemoon_blocksy_page_url( 'magazine' ) ); ?>"><?php esc_html_e( 'مجله', 'chidemoon-blocksy-child' ); ?></a>
			<a href="<?php echo esc_url( chidemoon_blocksy_page_url( 'guides' ) ); ?>"><?php esc_html_e( 'راهنمای خرید', 'chidemoon-blocksy-child' ); ?></a>
			<a href="<?php echo esc_url( chidemoon_blocksy_page_url( 'comparisons' ) ); ?>"><?php esc_html_e( 'مقایسه‌ها', 'chidemoon-blocksy-child' ); ?></a>
			<a href="<?php echo esc_url( chidemoon_blocksy_page_url( 'shop' ) ); ?>"><?php esc_html_e( 'فروشگاه', 'chidemoon-blocksy-child' ); ?></a>
		</nav>
	</section>
</main>
<?php
get_footer();
