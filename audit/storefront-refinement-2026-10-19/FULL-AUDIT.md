# Page and process audit — local review

Date: 2026-10-19. Review URL: http://localhost:18098. Production access in this pass was read-only; no production deployment or draft publication.

## Coverage and evidence

Full-page scroll and screenshots for 17 routes at 320, 360, 390, 768, 1024, 1440 and 1920 px: home, categories, shop, guides, comparisons, shop-the-look, magazine, guide article, HG799 product, product comparison, mixed search, filtered product search, product category, comparison article, long-title/multi-image product, product chooser and 404. All 119 combinations had no document horizontal overflow, broken visible images or uncaught page errors. The deliberate missing route returned 404 in all seven widths. This checks these routes and data, not every catalogue item.

Raw results: `output/playwright/final-audit-results.json`; screenshots `audit-WIDTH-INDEX.png` in the same directory. Later fixes were rechecked separately at 320, 390 and 1440 px: `recheck-interactions.log` and `recheck-*.png`. Local scene data was absent during the initial sweep; a public scene was subsequently mirrored and tested successfully at all three widths.

## Fixes in this pass

- Mobile shop and related products use two columns. Product titles are capped visually at three lines; full title remains in the link. Grid rows size independently, avoiding uniformly tall rows caused by one long title.
- Mobile filters collapse behind an accessible button, retain applied count and close with Escape. Desktop retains the wider sidebar without an internal category scrollbar. Reversed price bounds show an inline error. Apply requires a valid changed state; reset requires applied filters.
- Product-search filters now actually affect the main search query and retain search text/type across apply and reset. Search facets were restored to the product-search template. Browser verified that a one-million-toman cap reduced the lamp search to HG799 and C801.
- Long product descriptions expand/collapse on narrow layouts; focus entering hidden content expands it. Product review form spacing, field layout and labels improved. Operational timestamps/source rating were removed from the fallback observations UI.
- Comparison omits seller rows, operational observation fields and unknown facts. Its table no longer traps specification rows in an inner vertical scroller, including mobile.
- Product chooser hides empty facet groups and unknown dimension labels, displays toman instead of IRT, labels dimensions in centimetres and provides a useful empty result state. Its paginated results support automatic continuation.
- Shared card actions use the relevant content type. Article table of contents uses a generic label appropriate to guides and comparison articles.
- Scene image rendering now falls back to the original attachment when optional WebP metadata is missing or malformed. Previously an empty metadata string could cause a PHP TypeError and stop rendering the page. Added a regression test for absent, malformed and valid variants.

## Elementor work preservation

Read-only export of production autosaves and their published parents is retained under ignored `output/playwright/export-editor-snapshots.json`. Two autosaves newer than their parents were restored as separate local drafts: guides #506 and article-card template #508. Older shop-the-look and comparisons autosaves were preserved in the export, not restored over newer published documents. Production work was not overwritten.

Local draft #506 was opened in Elementor, its heading edited and saved using **Save Draft**. Its status remains draft. Evidence: `output/playwright/elementor-draft-saved.png`. Server autosaves can be recovered this way; edits existing solely in an unsaved browser tab are not present in the server export.

Reproducible local-only tools: `import-editor-review.php`, `sync-local-review-pages.php`, `import-public-review-products.php`, `import-public-review-scenes.php`. They remap local references and preserve previous local page data. Published production products #767 and #770 were copied to local #510 and #517 to exercise real long titles and a six-image gallery; this does not change their production records. The originally quoted 120-piece cutlery product was not found among current public source products.

## Processes and tests

- Shop filtering, search filtering, comparison selection/persistence/update and scene popovers exercised in a real browser. Existing automatic shop continuation and gallery/lightbox checks are recorded in README.md.
- Submitted a local WooCommerce review through the actual visible star controls and form. WordPress created review #7 pending moderation with rating 4. The review fixture was permanently removed immediately after verification.
- Cart, checkout and account route to the shop by intentional affiliate-site behavior; there is no internal purchase checkout to test as a conventional shop transaction.
- 36 Node tests / 10 suites pass. Linux host-operation suites excluded on this Windows host; see README.md.
- 12 PHP regression scripts pass, including the new scene-image test. Modified PHP/JS syntax checks and `git diff --check` pass.

## Remaining findings

- Production catalog synchronization is running: last successful import observed at 2026-10-19 16:20 UTC, with no blocked/retry state. The returned source stock observations for sampled products were from October 14–15. They correctly become unknown under the existing 24-hour freshness rule. Refreshing authoritative upstream observations is required to establish current stock; no freshness dates or availability were fabricated. The chooser intentionally requires fresh price/stock evidence and therefore may have no eligible matches with this dataset.
- Elementor 4.2.4's own checklist script logged a null `parentElement` error in the editor. Editing and saving the restored draft still succeeded. Vendor files were not patched or errors hidden.
- Existing editorial conversion preflight reports 22 complex legacy article bodies needing manual migration; the general Elementor verifier still flags legacy post #97. Those article structures were not bulk overwritten.
- Separate concurrent typography, AI and look-optimization work was retained. No whole-file rollback or deployment was performed.
