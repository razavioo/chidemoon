# Responsive size review — 2026-10-19

The first typography pass fixed font loading and very small text, but did not sufficiently correct relative sizes. This follow-up was applied to the local review site only.

## Findings in the actual rendered pages

- Mobile homepage and guides: section headings were 32px, larger than the 26px H1. A generic H1 selector overrode the hero size.
- Native article H2 widgets inherited 32px on both desktop and mobile, making them larger than the mobile article title. Their 44.8px/19.2px prose margins were added to Elementor's separate 20px container gaps.
- Generated native article cards had explicit 20px/700 typography in Elementor data, so changing only the baseline stylesheet could not reliably resize them.
- Product names were 14px on desktop while prices were 19px/700, creating excessive emphasis on prices. The product detail title used 1.9 line-height, which spread long titles out unnecessarily.
- Button labels had 14px text in a 14px line box. Comparison titles, footer branding and product section titles used unrelated sizes.

## Changes

The source of size roles is now `--ch-type-*` in `public-design.css`. Desktop/tablet/mobile values preserve the page > section > card hierarchy. Hero 40/28px, page 36/28px, section 28/22px, article title 36/26px, article H2 24/22px, editorial card 18/17px, prose 17/16px, product detail title 22/20px, product card title 15/14px and card price 18/16px (desktop/mobile).

The broad H1 override was removed. Native article heading widgets no longer receive both prose margins and container gaps. Buttons have 1.5 line-height and retain their target height. Card prices use weight 600; product names use an available regular weight. Footer branding is quieter. Legacy editorial feeds and product/compare section headings follow the same role scale.

`tools/type-scale-upgrade.php` updated only the three published managed article/product/search Loop templates still using the generated 20px/700 baseline without a custom mobile size. It saves one-time backups in `_chidemoon_before_type_scale_20261019` and uses native Elementor document saves. A second preview reports zero changes. Custom sizes, draft/review templates and unrelated settings are preserved. New templates in `elementor-editability-upgrade.php` use the revised native values.

## Verification and evidence

- 17 page states × six viewport widths (320, 375, 390, 768, 1024, 1440): 102 Chromium checks. Includes all public page types sampled previously, plus two/four-product comparisons.
- Checked the actual computed heading sizes: no sampled H2 was as large as or larger than its page's H1. No page-level overflow, unintended text font or sampled visible text below 12px was found.
- Visual review of viewport-sized before/after homepage, guide listing, article body and product detail screenshots. Final clean homepage/article/shop screenshots have the browser's temporary comparison selections cleared.
- Three existing Node Elementor ownership checks and PHP public-design card/query checks passed. PHP syntax checks passed for both migration tools; git whitespace checks passed. Native Loop migration is idempotent.

Before/after measurements: `output/type-scale-before.json`, `output/type-scale-after.json`. Full verification: `output/type-scale-verification.json` and the reproducible Playwright CLI script `output/type-scale-verify.js`. Screenshots: `output/playwright/scale-before-*`, `scale-after-*`, `scale-final-*`, and clean `scale-home-*`, `scale-article-body-*`, `scale-shop-*`.

Browser coverage is Chromium with representative records, including long imported product titles. Custom editorial size choices are intentionally preserved. Production was not changed.
