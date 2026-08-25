<?php
/**
 * Title: Product Comparison Article
 * Slug: kalahamoon/compare-side-by-side
 * Categories: kalahamoon-comparison, kalahamoon-review
 * Keywords: compare, comparison, head to head, ai, verdict, مقایسه
 * Viewport Width: 1280
 * Block Types: core/post-content
 * Post Types: post, page
 * Template Types: single, page
 * Description: The complete comparison blog post — intro, ranked product grid, AI head-to-head verdict, full spec table, FAQ, and verdict CTA. The mother template for any "X vs Y" or multi-product comparison.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'رو در رو: کدام را بخریم؟', 'kalahamoon' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'گزینه‌های نزدیک را در معیارهای مهم خرید مقایسه کردیم تا تصمیم شما آسان‌تر شود. در این مقدمه بگویید چه محصولاتی و بر چه اساسی مقایسه می‌شوند.', 'kalahamoon' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:kalahamoon/product-grid {"columns":3,"limit":3,"ranked":true} /-->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'داوری هوش مصنوعی', 'kalahamoon' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:kalahamoon/ai-compare {"align":"wide"} /-->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'جدول مقایسه کامل', 'kalahamoon' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:kalahamoon/comparison-table {"className":"is-style-bordered"} /-->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'هر گزینه برای چه کسی مناسب است؟', 'kalahamoon' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'برای هر محصول در یک یا دو جمله توضیح دهید چه خریداری بهتر است سراغ آن برود.', 'kalahamoon' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading"><?php esc_html_e( 'سوالات متداول', 'kalahamoon' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:kalahamoon/faq /-->

<!-- wp:kalahamoon/cta-button /-->
