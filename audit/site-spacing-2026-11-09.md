# Site spacing review — 2026-11-09

Published shared listing spacing tokens: 24px desktop/tablet, 16px mobile.
Applied to editorial listing grids, search result grids, catalog sidebar/results
and Look sidebar/gallery separation. Category landing and breadcrumb vertical
insets now follow the same spacing scale. Existing homepage section rhythm,
product-detail reading spacing and hero image geometry were retained.

The Look gallery's unequal image heights previously created large blank gaps
under shorter cards in grid rows. It now uses nonbreaking CSS columns, so every
card in a column is separated by exactly 24px (16px on phones). Source image
proportions and hotspot coordinates remain intact. Narrow tablets and phones use
one column; wide screens use two columns. Reading order flows down the right
column, then down the left column; single-column layouts follow source order.

Production Playwright verification:
- Look gallery at 320, 390, 768, 1440px: measured gaps precisely 16/16/24/24px;
  no horizontal overflow; product hotspots open at every size.
- Home, shop, categories, guides, comparison articles, magazine, populated product
  comparison, an article, a product category and search at 390 and 1440px:
  no horizontal overflow; listing gaps correctly resolve to 16/24px.
- Product detail at 390 and 1440px: no horizontal overflow.
- Desktop screenshot reviewed: output/spacing-looks-desktop.png.

Only public-design.css changed for this task. Prior uncommitted work preserved.
Production backup: /tmp/before-spacing-review.css. Public cache invalidated.
