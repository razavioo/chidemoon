# Shared homepage comparison and category additions

Published 12 requested product categories, IDs 272–283, via idempotent `tools/shop-look-categories.php`. The first three are children of existing furniture; the remaining categories are roots. Existing similarly named categories and product assignments were preserved. All 12 terms verified by a second preview run. Categories are available for product classification and shop-the-look product selection; no products or hotspot associations were invented.

Replaced the homepage-only table CSS with the comparison page's actual `chidemoon-comparison-table` / `chidemoon-comparison-table-wrap` classes and asset. Kept editorial use-case rows. Both page types render the same SVG removal icon. Homepage removal deletes the corresponding column in every row, supports the empty state and restore, moves focus, announces removal, and does not alter the user's saved comparison selection. The homepage table uses a separate wrapper so comparison workspace layout rules and query/history behavior do not affect it.

Native Elementor comparison section changed from full to boxed, matching the other homepage sections. Existing shared layout now provides identical 1280px content widths (x80/right1360 at1440), 32px vertical section padding and 16px internal gap; mobile content x16/right374 at390, 24px padding. All section headings use YekanBakh at27px desktop /21px mobile. Product and editorial card titles retain their appropriate smaller hierarchy. Checked entire homepage including hero, shoppable cards, comparison, products, magazine and bottom banner.

Live verification at1440,1024,390: no document overflow; matching section/table edges; both removals, row cell counts, empty state, restore, unchanged saved selection. Computed table styles exactly match comparison page at1440/390: font13/12, line24.7/22.8, padding20x24/14x12, images140/96, removal36px circular. Main comparison removal also passes. PHP lint, JS syntax and diff checks pass. Full screenshots and comparison screenshots: `output/playwright/home-unified-*`; loaded full-page capture `home-unified-loaded-1440.png`.

Native page backup `_chidemoon_before_home_spacing_20261104`; code backup `/opt/chidemoon/backups/home-unified-20261104/code.tar.gz`.

## General category navigation follow-up

The 12 product terms were initially outside the curated `chidemoon_category_coverage_ids` navigation scope. Published `tools/site-category-navigation-expand.php apply` to merge all requested categories and their ancestors into that shared scope, preserving previous categories. Original scope saved in `chidemoon_category_scope_before_general_expansion_20261104`. Cleared Elementor element caches. Live browser verified all 12 category links on `/categories/` and the homepage header; desktop/mobile category page screenshots checked, no horizontal overflow.
