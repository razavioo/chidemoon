# Public typography revision — 2026-10-19

The initial size choices below are superseded by the follow-up [responsive scale review](scale-review.md). The font installation and provenance remain unchanged.

Applied to the existing local site at http://localhost:18098. No production deployment was performed.

## Result

The owner supplied licensed YekanBakh files from `ai-agent/client/public/fonts`. Regular, SemiBold, Bold and ExtraBold are hosted locally and registered in Elementor's picker. Both shared font roles and the native kit use YekanBakh. Standard numerals preserve identifiers such as HG 799; prices/dates retain application localization. Legacy fonts remain available to explicitly styled content.

The former Estedad/Vazirmatn mixture, 11px mobile actions, 12px product titles, negative Persian heading tracking and heavy price weights were the main typography issues. Heading rhythm is now 1.6–1.75, prose keeps its reading rhythm, mobile product titles are 14px, product actions 13px with 44px minimum height, and mobile text inputs are 16px. Prices use 700, with 800 retained for the wordmark. Product UI headings use the body role. Responsive article/hero titles and editorial card line heights were corrected.

## Existing and fresh installations

`tools/typography-upgrade.php` previews changes by default; append `apply` to save through native Elementor documents. It creates one-time backups under `_chidemoon_before_typography_20261019[_kit]`, replaces only project legacy families, preserves other settings and independently chosen font families, and clears generated CSS. The active local kit was updated; no documents contained explicit legacy family overrides. A repeated preview reports no pending updates. Fresh site defaults in `tools/elementor-rebuild.php` match this kit.

## Verification

- Real Chromium via Playwright CLI: 17 page states at widths 320, 375, 768, 1024 and 1440 (85 checks). Includes home, shop, actual HG 799 product, product category, category browser, magazine, guides, editorial comparisons, empty product comparison, two/four selected products, looks, guide article, look detail, search results, empty search and 404.
- No page-level horizontal overflow, unexpected text font family, or visible sampled text below 12px in these checks. Loaded text font faces were YekanBakh. This checks representative routes and visible text elements, not every catalogue record or browser engine.
- Additional states: mobile navigation open, mobile search open (input measured 16px), actual Shop-the-Look product tooltip open, and homepage at CSS zoom 200%; none had page-level horizontal overflow.
- Screenshots visually reviewed for desktop homepage/product, mobile shop/guide, two-product comparison and mobile navigation. Comparison tables intentionally keep their own scrolling area.
- Three native Elementor ownership Node checks passed. Existing PHP public-design card/related-query checks passed. PHP syntax checks passed for migration, rebuild tool and public-design class; targeted git whitespace check passed.

Evidence: `output/typography-browser-review.json`, `output/typography-states-review.json`, reproducible CLI scripts under `output/typography-*.js`, and `output/playwright/typography-*.png`. Main screenshots: `typography-0-1440.png` (home), `typography-1-375.png` (shop), `typography-2-1440.png` (product), `typography-10-375.png` (guide), `typography-15-375.png` (two-product comparison).
