<?php
/**
 * Title: Product Review Article
 * Slug: kalahamoon/page-product-review
 * Categories: kalahamoon-review, kalahamoon-product
 * Keywords: review, product, verdict, pros, cons, نقد, بررسی
 * Viewport Width: 1280
 * Block Types: core/post-content
 * Post Types: post, page
 * Template Types: single, page
 * Description: The complete single-product review blog post — intro, product card, rating, pros & cons, section-by-section analysis, buyer fit, comparison, FAQ, and verdict CTA. The mother template for any review article.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<!-- wp:paragraph -->
<p><?php esc_html_e( 'مقدمه کوتاه — کاربر دنبال چه چیزی است و این نقد به کدام سوال‌ها پاسخ می‌دهد. در دو تا سه جمله انتظارات خواننده را روشن کنید.', 'kalahamoon' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:kalahamoon/product-box {"layout":"horizontal"} /-->

<!-- wp:kalahamoon/rating-box /-->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'مزایا و معایب در یک نگاه', 'kalahamoon' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:kalahamoon/pros-cons /-->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'تحلیل بخش‌به‌بخش', 'kalahamoon' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'نقد را در بخش‌های جداگانه‌ای مثل طراحی، کیفیت ساخت، عملکرد، ارزش خرید و خدمات پس از فروش سازماندهی کنید. برای هر بخش یک عنوان فرعی بگذارید.', 'kalahamoon' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'طراحی و کیفیت ساخت', 'kalahamoon' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'متن خود را اینجا بنویسید.', 'kalahamoon' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'عملکرد در استفاده روزمره', 'kalahamoon' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'متن خود را اینجا بنویسید.', 'kalahamoon' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'چه کسانی باید این محصول را بخرند؟', 'kalahamoon' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'برای خریدارانی که ترکیب مناسبی از قیمت، کیفیت و کاربری روزمره می‌خواهند، این محصول گزینه خوبی است. اگر ویژگی‌های تخصصی‌تری لازم دارید، گزینه‌های جایگزین را هم مقایسه کنید.', 'kalahamoon' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'مقایسه با گزینه‌های مشابه', 'kalahamoon' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:kalahamoon/comparison-table /-->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'سوالات متداول', 'kalahamoon' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:kalahamoon/faq /-->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'جمع‌بندی', 'kalahamoon' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'نظر نهایی خود را در یک پاراگراف کوتاه بنویسید و خواننده را به سمت تصمیم خرید هدایت کنید.', 'kalahamoon' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:kalahamoon/cta-button /-->
