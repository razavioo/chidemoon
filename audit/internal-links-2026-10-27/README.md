# Internal linking review — 2026-10-27

Read-only production review; no site content or configuration changed.

Confirmed findings:
- 534 published products; 6 published posts; 3 native looks; 2 scene records.
- Existing content-relations system connects 8 products to editorial/look context. This is not an orphan-page count.
- None of the six posts has product references registered in that system.
- Live HTML of both guides (bedroom-lighting-guide, dining-table-size-guide) has zero product links in main content. Both comparison articles link to the other comparison but not products in main content.
- Shop page 1 links only to pages 2–5. Page 5 pagination lists only 1–5 and has no next-page link. Pages 6 and 34 nevertheless return HTTP 200 and show 16 and 6 distinct products, respectively. Thus normal shop pagination stops after the first 80 products; other paths may still reach remaining products.
- All 35 distinct category links from /categories/ return HTTP 200 at their original URLs.
- Homepage, shop, guides, comparisons, shop-the-look, categories and sampled editorial pages return HTTP 200.
- Some image-only card anchors have no accessible text/alt in fetched HTML, although a separate descriptive title anchor exists for the same destination.
- Product-to-scene context uses /shop-the-look/#ch-scene-ID. Appropriate for navigation; fragment identifiers are not separate indexable landing pages.

Limits: not a full graph crawl or Search Console/indexation review; no claim that all links are healthy or any product is truly orphaned. Inventory JSON captures registered relations, not every rendered hyperlink.

Breadcrumb rollout: added shared editorial breadcrumbs and JSON-LD for pages, posts, looks, taxonomy and search routes. WooCommerce routes retain their native output; homepage omitted. Rendering occurs outside Elementor shared template caching, with per-request trail captured before output buffering. Sequential and parallel live checks confirm article-specific labels and one navigation/schema per page across guides, comparisons, categories, look hub, shop, product category and product samples. Homepage has neither. Mobile 390px visual check passed with no overflow; desktop screenshot saved. PHP lint and git whitespace checks passed. No content records changed.

Structural links rollout (2026-10-27): preserved existing header links to categories, shop, comparisons, looks and guides. Added dynamic crawlable category hub links on homepage, parent/child/sibling category navigation on category pages, and category ancestry plus six visible published same-category product links in the product template. Blocks render per request outside Elementor cache. Removed archive pagination limit and enabled previous/next with shortened numbers; backed up original document in _chidemoon_before_structural_links_20261027 and templates-before.json. Live crawl fetched all 34 shop pages HTTP 200 and discovered 534 distinct product URLs, with six on page 34; page 5 now links forward to page 6 and final page 34. Homepage/category/product samples each render exactly one structural block with 14/3/10 links respectively. Mobile no document overflow. PHP lint and git diff whitespace checks passed. Screenshots structural-home-desktop.png and structural-home-mobile.png. Editorial contextual linking remains separate from this structural implementation.
Destination checks: all 48 distinct header/home structural targets HTTP 200 without redirects; all 10 product-template structural targets in the sample HTTP 200.
