# Newly imported product categories — 2026-10-22

Applied the reviewed plan to the exact 135 Basalam-backed products created on 2026-10-21. Checked identity, title and draft status before writes. Existing reviewed classifications would stop the first run. Categories were saved through the enrichment API with source references; existing tags, non-default categories and unrelated editorial fields were preserved.

131 products received product categories. Four merchant-name-only entries (4933, 5005, 5810, 6551) have empty descriptions/specifications, so they received the explicit “نیازمند بررسی اطلاعات کالا” category rather than a guessed product type. Created missing categories for blankets, sofa throws, furniture/sofas, dishwashers, kitchen faucets, appliance covers and Haft Sin serving sets under appropriate existing parents.

All 135 also received the product tag “واردشده از کالاهامون ۲۰۲۶-۱۰-۲۱” (slug `kalahamoon-import-2026-10-21`, ID 206), allowing a native product-list filter. All remain drafts.

Verification: each category/tag assignment was read back after applying, batch tag membership equals exactly these 135 IDs, status remained draft, and unrelated enrichment fields were unchanged. A separate fresh WPCLI process confirmed 135 drafts, zero Uncategorized assignments, and exactly four review entries. See `plan.json` and `result.json`.

Production backups: `/opt/chidemoon/backups/categories-20261022/wordpress.sql` (26,446,441 bytes), `preview.json` with prior terms/enrichment/tokens, and `result.json`. Each product also retains its previous classification in `_chidemoon_categories_before_20261022`.

Operational tool: `tools/apply-new-product-categories.php`, preview by default, writes only with `apply`. Deploy `plan.json` alongside it as `new-product-categories-plan.json`. No publication, merchant data or source content changes were made.
