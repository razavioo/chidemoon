# Three-level category coverage — 2026-10-19

Implemented the user's four screenshots: both red backgrounds and red text define coverage. The 34 menu terms include required ancestors; unmarked existing categories and their products are retained outside the scoped menu. Small-appliance intermediate grouping is omitted to keep preparation/drinks and their specific types within three levels. Dinnerware is directly below kitchen essentials, with five material categories as the third level. Bedroom/bedding/bedspread and textiles/rugs/machine-made carpet complete the scope. Empty covered categories remain visible.

`tools/category-coverage.php [apply]` creates/reparents by stable slug, preserves existing term IDs and slugs, saves original taxonomy/menu settings and asserts unchanged product relationships. Existing heater term 92 contains multiple fuels, so its broad identity is preserved outside the scoped menu; separate gas/electric leaves avoid relabeling all heaters as electric.

`tools/category-coverage-products.php [apply]` adds specific categories to 105 published products using explicit product-title evidence and the existing Enrichment approval API. Original categories and enrichment are backed up in per-product metadata. Manual and mixed-fuel electric classifications are excluded. No existing assignment is removed. A subsequent dry run reports zero pending product updates.

Navigation uses links for destinations and separate expansion buttons. Desktop has a root rail and three readable subgroup columns. Mobile/tablet expand roots and subgroups independently, with 44px leaf targets, focus outlines, Escape and desktop directional keyboard navigation. Narrow header menus scroll within the viewport. Supplemental CSS/JS are separate files to preserve unrelated storefront work.

Production deployment used a surgical patch of the existing discovery class (enqueue and category query only); unrelated local public-design and AI changes were not deployed. Production originals and database dump: `/opt/chidemoon/backups/category-coverage-20261019/`. Classification details are in `product-classification.log` there. Local review database also has the category migration.

Verified on https://chidemoon.com/categories/:

- 34 scoped terms, maximum actual taxonomy depth three; all 34 archive URLs return HTTP 200.
- 389 published products retained; all 105 updated products remain publicly eligible, match reviewed category metadata, and preserve their original assignments.
- Header interaction and third-level link reachability at 320, 390, 768, 820, 1024, 1100 and 1440px; document scroll width equals viewport width throughout.
- Landing page desktop/tablet/mobile visually inspected; header desktop/mobile/tablet screenshots inspected. Desktop ArrowLeft enters the detail panel and Escape returns focus to the selected root.
- JavaScript syntax and PHP lint passed; changed tracked file whitespace check passed.

Screenshots: `output/playwright/category-coverage-live-{desktop,tablet,mobile}.png`, `category-coverage-live-header-desktop.png`, `category-coverage-live-header-{390,820}.png`. Earlier header-mobile screenshot predates the scroll fix; the numeric-width screenshots are the current verification.
