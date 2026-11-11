# Merchant reconciliation and mobile performance — 2026-11-07

Changes were applied to production. Merchant prices and availability are a point-in-time observation, not a guarantee of future merchant changes.

## Catalogue

- Started with 734 published products: 732 Basalam and 2 Digikala.
- Verified all 732 Basalam identities against the original product page's server-rendered product data. Checked price against the page's JSON-LD offer; all currencies were IRR. Converted rial to WooCommerce IRT/toman without changing the denomination.
- Found 269 changed Basalam prices (256 among the retained products). Refreshed observations on all 732.
- Verified both Digikala products in the rendered merchant pages: DKP-524436, black, main buy-box seller, 899,000 toman; DKP-563439, black, main buy-box seller, 1,192,710 toman. Both had an active Add to cart button. The latter also has other sellers with different prices; the site reflects the default merchant offer, not an invented minimum across sellers.
- Corrected 271 differing prices in total, including products subsequently removed from public display.
- Confirmed 45 Basalam products were not saleable. Some reported `isAvailable=true` but `isSaleable=false`; these were also excluded. Vendor inactivity and non-public product status were checked.
- Set these 45 products to draft and hidden. No irreversible deletion. Kept prior publication status/visibility and audit reason in product metadata.
- Final published count: **689**. Rechecked all 689 prices after a complete successful source sync: **zero mismatches** with the captured merchant evidence. No confirmed unavailable products remained published.
- Updated the exact tenant/connector's versioned source records and associated listings, as well as WordPress, to prevent the next import restoring old values. Twelve records absent from that connector and the two Digikala records were reconciled through their existing WordPress identities without fabricating upstream mappings.

Evidence: `output/catalog-performance-2026-11-07/source-results.jsonl`, `digikala-verified.json`, `inventory-verified.json`, `source-before.json`, `reconciliation-plan.json`. Digikala screenshots are under `output/playwright/digikala-*.png`. Merchant evidence was collected on 2026-11-07, before applying the reconciliation.

Backups on the production host: `/opt/chidemoon/backups/catalog-performance-20261107/` contains the pre-change WordPress database, scoped source/listing JSON, previous core plugin, previous sync code, and previous wp-config. Do not restore the whole database over later editorial changes without checking them first.

## Performance changes

- Generated non-destructive responsive WebP derivatives for the current catalogue and galleries; retained original media. The optimization run processed 4,998 usable images; the complete manifest now contains 5,510 optimized attachments, including earlier derivatives.
- Corrected picture/source sizing for the homepage's half-width scene cards and used the responsive derivatives for scene images. Loaded the first visible scene images and product photo eagerly with high priority.
- Made the first WooCommerce gallery photo visible before slider JavaScript finishes. Hide additional slides only before the slider viewport exists, to prevent an initial stack of photos.
- Inlined local styles at their original cascade position to remove many blocking stylesheet requests. Preserved media attributes and relative asset URLs, rejected external files and CSS imports, and capped the embedded size. Five targeted CSS URL/cascade/media assertions passed.
- Installed an anonymous HTML cache with a five-minute maximum lifetime. Product/post/taxonomy changes invalidate it immediately. Requests with cookies, authorization, query parameters, or non-GET methods bypass it; admin, REST, search, redirect/affiliate routes are outside its route allowlist. Cookie and query bypass was verified on all four tested pages.
- Initial server-side check: cache hits returned the document in approximately 63–80 ms, compared with 0.75–2.14 seconds on uncached requests. These are server-to-server timings, not mobile LCP. Cold requests remain slower.
- Found and fixed source acknowledgement timeouts: 1,000-record transactions exceeded the HTTP timeout. New and already-pending receipts are delivered in batches of 100 with durable ordering. All **32 sync recovery scenarios** passed, including partial failure and token rotation. Production sync completed successfully at **15:02:56 UTC**, and the post-sync catalogue still matched the audit.

## Mobile Lighthouse

Lighthouse 13.6.0, Chrome, default mobile simulated throttling. Baseline and subsequent HTML/JSON reports are saved under `output/catalog-performance-2026-11-07/`. These are laboratory measurements, with anonymous page cache warmed before the final runs; they are not a guarantee for every location, cold cache, network, or device. Intermediate runs varied and are retained rather than discarded.

| Page | Initial LCP | Final LCP | Final CLS | Final performance |
| --- | ---: | ---: | ---: | ---: |
| Home | 3.9 s | 2.48 s | 0.073 | 94 |
| Mirrors category | 2.7 s | 2.27 s | 0.071 | 96 |
| Product HG 799 | 7.2 s | 2.03 s | 0 | 98 |
| Shop the Look | 1.7 s | 1.6 s | 0.071 | 96 |

Final report filenames: `lh-home-verified`, `lh-category-verified`, `lh-product-verified`, `lh-looks-complete` (each `.report.json` and `.report.html`). The home result is close to the 2.5-second boundary; prior optimized runs ranged above it. Keep monitoring rather than describing this as a universal pass.

**INP:** field INP could not be verified through CrUX/PageSpeed. Lighthouse TBT is not INP. An Event Timing browser check at 390×844 with 4× CPU slowdown measured search and filter interactions at a maximum of 152 ms (home 88, category 152, product 72, looks 40). These sampled laboratory interactions met 200 ms; they do not establish the field INP percentile.

## Browser checks and external services

- Rechecked all four pages at widths 320, 390, and 768 pixels (12 page/viewport combinations). No horizontal page overflow. Verified responsive style application, gallery dimensions, and scene hotspots. Screenshots and results: `output/playwright/performance-final-*`, `output/catalog-performance-2026-11-07/final-responsive.txt`.
- Earlier full mobile functional coverage, comparison, two-filter intersection/removal/clear and merchant navigation are recorded in `audit/mobile-qa-2026-11-07.md`. No physical phone was available.
- PageSpeed Insights homepage analysis returned **“Unable to resolve https://chidemoon.com/”**. Its API also returned quota exhaustion. A later retry of the service returned HTTP 403, so tests of the remaining URLs through that service could not be completed. No PageSpeed score or field data is claimed.
- GTmetrix returned a Cloudflare challenge/403 before its test form could be used. No GTmetrix score is claimed and the challenge was not bypassed.
- Lighthouse successfully tested all four requested pages. External-service failures are separate from the site's measured laboratory results.
