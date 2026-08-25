<?php
/**
 * Title: Best-of Roundup & Buying Guide
 * Slug: kalahamoon/page-category
 * Categories: kalahamoon-product, kalahamoon-editorial
 * Keywords: category, roundup, best of, listing, buying guide, دسته‌بندی, بهترین, راهنمای خرید
 * Viewport Width: 1280
 * Block Types: core/post-content
 * Post Types: post, page
 * Template Types: page, archive
 * Description: The complete "best X" roundup / category buying-guide blog post — intro, ranked product grid, buying criteria, per-pick notes, FAQ, and verdict CTA. The mother template for list and category articles.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<!-- wp:paragraph -->
<p><?php esc_html_e( 'در این راهنما بهترین گزینه‌های این دسته را بر اساس ارزش خرید، کیفیت، کاربرد و بازخورد خریداران مرور می‌کنیم. در مقدمه بگویید لیست بر چه اساسی رتبه‌بندی شده است.', 'kalahamoon' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'انتخاب‌های برتر', 'kalahamoon' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:kalahamoon/product-grid {"columns":3,"limit":5,"ranked":true} /-->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'قبل از خرید به چه معیارهایی توجه کنیم؟', 'kalahamoon' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul><!-- wp:list-item --><li><?php esc_html_e( 'بودجه و هزینه نگهداری', 'kalahamoon' ); ?></li><!-- /wp:list-item --><!-- wp:list-item --><li><?php esc_html_e( 'کیفیت ساخت و دوام', 'kalahamoon' ); ?></li><!-- /wp:list-item --><!-- wp:list-item --><li><?php esc_html_e( 'گارانتی، ارسال و خدمات پس از فروش', 'kalahamoon' ); ?></li><!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'چرا هر گزینه در این لیست است؟', 'kalahamoon' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'برای هر انتخاب در یک پاراگراف کوتاه توضیح دهید چرا جای خود را در لیست دارد و برای چه کسی مناسب است.', 'kalahamoon' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'سوالات متداول', 'kalahamoon' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:kalahamoon/faq /-->

<!-- wp:kalahamoon/cta-button /-->
