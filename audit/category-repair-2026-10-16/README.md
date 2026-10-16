# Category hierarchy and navigation repair — 2026-10-16

Reviewed all 389 published product titles/category assignments and the WooCommerce category tree.

- Cutlery term 96 was a child of serving term 94. Moved it under kitchen essentials 93, so serving no longer includes cutlery through descendants. Cutlery now has 41 products; serving has 3. Handmade carpets term 165 now belongs under rugs 114.
- Corrected five product categories through Enrichment::apply with existing source URL evidence: 837 heater (incorrectly a rug because model title contains فرش); 770 cutlery storage box (drawer/cabinet organizer); 587 dinnerware set; 882 and 883 explicitly manual food processors (new manual food-preparation category under kitchen essentials).
- Replaced product/post taxonomy Loop Item bodies 517/521 with a single dynamic category-title link; no images, cards or buttons. Source and archive URL bindings retained. Panel 525 uses 3 columns desktop / 2 mobile, 13px normal-weight links, 14px section headings and content-sized rows.
- Updated the reusable native-template generator so new templates retain the same simple links. CSS deployed only after confirming production baseline. Production original CSS: /opt/chidemoon/backups/category-repair-20261016/public-design.css.before.
- Pre-change parent IDs are stored in _chidemoon_category_parent_before_20261016_{ID}; original enrichment and Elementor trees in _chidemoon_before_category_repair_20261016 / _chidemoon_before_category_list_20261016 metadata. No products deleted or unpublished.
- Full installed Elementor acceptance passes; all 389 published products pass readiness and public eligibility; zero cutlery products in serving descendants. Native-template generator PHP lint and git diff --check pass.
- Actual browser desktop and mobile: 30 category/topic text links, 0 category buttons, 0 category images, 13px links, no horizontal overflow. Verified mobile dropdown expands and category-name click opens the 41-product cutlery archive. Existing old cutlery and handmade-carpet URLs still return HTTP 200.
- Screenshots: output/playwright/category-menu-desktop.png and category-menu-mobile.png.
