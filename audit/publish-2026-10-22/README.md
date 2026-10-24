# Publish imported products — 2026-10-22

The user explicitly requested publication of the newly imported batch. Published 118 identifiable products with merchant destinations, approved categories and real featured images. Set catalog visibility to `visible` and review state to `reviewed`, using normal WooCommerce saves and the existing publication gate. Preserved existing reviewed fields; any unspecified required classification without saved terms was explicitly reviewed as `unknown`, without inventing attributes.

17 remain drafts: 13 have no source images or featured image (4497, 4784, 4785, 5047, 5218, 5311, 5388, 5389, 5465, 5580, 5812, 6423, 6653); four are merchant-name-only entries (4933, 5005, 5810, 6551). Publication safeguards were not bypassed.

Verified all 118 through WooCommerce: published, catalog visible, public eligibility, visible product and available merchant offer. A fresh WPCLI preview confirmed all 118 remain published/visible. Anonymous HTTP requests for IDs 4447, 5664 and 6704 returned 200 with their actual titles/product markup. The batch public tag archive returned 200 and listed published products.

Public batch URL: https://chidemoon.com/product-tag/kalahamoon-import-2026-10-21/

Backups: `/opt/chidemoon/backups/publish-20261022/wordpress.sql`, `preview.json`, `result.json`, `verification.json`. Previous publication state and classification are also retained per product in `_chidemoon_before_publish_20261022`. Operational script: `tools/publish-import-batch.php`, preview by default, explicit `apply` to publish. See `result.json` for every published URL and held product.
