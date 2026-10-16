# Publication repair — 2026-10-16

Production: https://chidemoon.com

- Full database and uploads backup completed before writes: `database-20261016T160441Z.sql.gz`, `uploads-20261016T160441Z.tar.gz`; checksums verified.
- 389 products approved through `Chidemoon_Core_Enrichment::apply`, checked for readiness and published through WooCommerce CRUD. Source-backed category/room classifications; unspecified style, color and material explicitly remain unknown.
- Missing source image for product 980 recovered. Product 443 identity reconciled against its existing merchant URL.
- 12 drafts retained: duplicate 721 (original 706), and 11 records lacking merchant source identities/URLs. See `remaining-products.csv`.
- WooCommerce `woocommerce_coming_soon` changed from `yes` to `no`; previous value backed up in `_chidemoon_pre_store_launch_20261016`.
- Related-product Elementor query recursively re-entered itself while ranking candidates, causing Apache/PHP crashes and HTTP 502. Added an exception-safe reentrancy guard, with regression coverage. Original production file saved at `/opt/chidemoon/backups/publication-20261016/class-chidemoon-core-public-design.php.before`.
- Targeted PHP regression and public card tests passed; 46 Node tests passed in Linux with PHP available.
- Existing source synchronization reports `authorization_required` (last successful pull 2026-10-13). This operation does not falsify current prices or availability or rewrite connector credentials.

Operation scripts preserve pre-change metadata and use existing enrichment/publication gates. `result.json` records publication counts and local image-file/readiness checks. Screenshots are in `output/playwright/`.
All 389 published products passed final readiness, local image file, public eligibility, visibility and structured Elementor body checks. 381 source-linked bodies were created, 4 existing product bodies converted, 4 preserved. The broad legacy Elementor verifier passes product bodies but stops at its unrelated old Shop-the-Look scene-image assertion; full verifier success is not claimed. Browser checks confirmed public listing, page 2, 61 heating products, and detail pages for products 1014 and 853 on desktop/mobile.

## Follow-up repairs
- Existing encrypted OAuth access successfully refreshed; unblocked and completed an actual catalog poll at 2026-10-16T18:13:23Z: failed 0, created 0, updated 1, pending receipt batches 0. All 389 approvals preserved.
- Renew the access token after potentially long media imports before acknowledging receipts. Isolated expired-token regression passes against both local and production code. Production already contained additional token persistence/backoff safeguards absent from local HEAD; preserved those and applied only the receipt renewal change. Original saved to /opt/chidemoon/backups/publication-20261016/class-chidemoon-catalog-sync.php.before.
- Updated Elementor acceptance verifier to resolve normalized Look image/relationships and product IDs while checking source identity. Full installed acceptance checks now pass; see elementor-final.log. Production content required no changes for this verifier correction.
- Archived duplicate 721 to WordPress trash (recoverable; original 706 retained). Counts: 389 published, 11 drafts, 17 trash. All 389 published products passed readiness and public eligibility after synchronization.
- Checked missing-source products against Kalahamoon source mapping and delivery receipt tables: no records for the 11 product IDs. Original merchant links/identities still required; retained as drafts.
- Public HTTP checks: shop, product 1014 and shop-the-look each 200. No remaining publication integrity errors. No claim that stale upstream prices have been recaptured.
