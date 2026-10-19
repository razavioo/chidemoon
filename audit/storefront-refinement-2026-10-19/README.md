# Storefront refinement — local review, 2026-10-19

## Revised direction: Digikala reference

The user rejected the first visual direction and requested Digikala. This revision supersedes the original category/gallery styling below. Reference pages inspected: https://www.digikala.com/ and the H40 product page at https://www.digikala.com/product/dkp-20215441/ . Direct reference browser navigation encountered the site's client restriction; readable official page content was inspected through web access.

New native Elementor product template has three desktop columns: gallery, title/description/first six valid product facts, separate price/purchase panel. White surfaces, restrained borders, compact headings, neutral feature tiles, full-width purchase button, compact comparison action, and product section links. Gallery and native lightbox retained. On tablets the layout has two columns; mobile is one column. Description, full specifications and reviews are ordered consistently with section navigation.

Category menu now uses a right rail of main categories and a separate panel for nested category links. Leaf categories display up to three real published products sampled through one bounded query (maximum 60 products, shared within the request). Native SVG illustrations are small secondary accents. Desktop root hover/focus changes the panel; ArrowLeft enters it and ArrowRight returns. On narrow layouts original category links remain visible and the supplemental panel/artwork is hidden. Shop cards use a contiguous bordered grid and compact titles; search icon replaced with a conventional magnifier.

Apply after the earlier refinement: `docker compose run --rm wpcli --user=1 eval-file /tools/storefront-digikala-layout.php apply`. It saves a one-time template backup under `_chidemoon_before_digikala_layout_20261019`; a subsequent dry run reports already updated. No production writes.

Revision validation: PHP widget regression scripts and targeted Node contracts passed; syntax checks passed. HG799 desktop three-column layout and six factual feature tiles reviewed visually. Mobile at 390px and tablet at 1024px have no horizontal overflow. All three product section links resolve. New screenshots: `output/playwright/dk-product-desktop.png`, `dk-product-mobile.png`, `dk-categories-desktop.png`, `dk-shop-desktop.png`.

Local WordPress: http://localhost:18098. Docker Desktop started; existing database and uploads retained. Local WooCommerce coming-soon mode disabled to review the shop. Local currency option corrected from IQD to IRT without modifying amounts. No production writes or deployment.

Implemented:

- Frontend Persian text normalization removes combining terminal hamza and selected unnecessary joiners in visible Elementor text; link URLs, slugs and structured attributes remain intact.
- Removed stale-price caption, purchase commission notices, seller/source metadata from offer UI, unknown facts, observation timestamps and duplicate seller-description tab. Known stock states appear beside purchase; unknown stock is not asserted as available. Specification heading is «مشخصات محصول».
- Product gallery uses contained images, a neutral frame, thumbnail selection and native WooCommerce lightbox. Reduced product heading size; long short descriptions have a concise excerpt. Product data and imported source records retained.
- Category tree gains a restrained line illustration that changes on hover/focus, with desktop-only preview and an additional narrow-container fallback. Menu widened; mobile does not show illustrations. Inspiration reviewed: https://www.ikea.com/us/en/ and https://www.finnishdesignshop.com/en-us/ . Artwork is native SVG rather than downloaded brand assets.
- Automatic listing continuation supports Elementor numbered pagination without a next link, retains pagination on errors and without JS, and reinitializes appended Elementor widgets.
- Filter submission disabled until form state differs from initial state; reset hidden without applied filters. Desktop sidebar widened to 290px; internal category scrollbar removed. Select chevron inset and header search icon revised.
- Guide/article table of contents with anchor links and current-section tracking; inline placement on smaller screens. Reading buttons reflect article type; native card buttons aligned at bottom. Shop-the-look heading reduced; currency whitespace tightened.

Elementor data update: `docker compose run --rm wpcli --user=1 eval-file /tools/storefront-refinement.php apply`. It preserves a one-time `_chidemoon_before_storefront_20261019` backup and supports a dry run without `apply`. It updates the specific documents/settings and supplies the current category/sidebar structure for older local installations. A repeat dry run reported no pending changes.

Verified:

- 36 Node tests passed (excluding Linux shell/host deployment suites, which fail on this Windows host because shell tools/symlink privileges are unavailable).
- Four PHP regression scripts passed: commercial-recorded-price, product-offer-trust, product-actions, product-extra-tabs. PHP syntax checks and JS syntax check passed; git diff whitespace check passed.
- Browser: automatic shop continuation grew from 16 to 20 unique product cards, then stopped. Filter button enabled after editing and disabled after reverting; reset initially hidden. Desktop sidebar measured 290px. Category preview changed on hover and was hidden at 390px; no mobile horizontal overflow.
- Actual HG799 product: three thumbnails, thumbnail switch, lightbox open/Escape close; title 28px desktop; removed captions/notices absent. Guide TOC rendered and tracks the current section. Guide CTA bottoms matched at 1193.9375px across the first two cards despite differing title lengths.
- Screenshots under `output/playwright/`: hg799-refinement-desktop.png, product-refinement-mobile.png, categories-refinement-desktop.png, categories-refinement-mobile.png, categories-refinement-menu.png, guide-refinement-desktop.png, guides-refinement-cards.png.

Existing limitations found:

- Stock becomes unknown when an in-stock source observation is older than 24h (`FRESHNESS_SECONDS`). This change preserves source truth; reliably current stock requires refreshed authoritative observations. It does not fabricate availability or change freshness policy.
- Local catalogue has 20 products and does not contain the supplied cutlery product; the shared product template was verified using HG799 and existing products.
- Full Elementor verifier stops on existing article #97 missing native-editor mode. Editorial migration dry run reports 22 existing articles requiring manual handling and no convertible plans. These legacy editorial issues were not overwritten. Logs: `output/storefront-elementor-verification.log`, `output/storefront-editorial-preflight.log`, `output/storefront-node-tests.log`.
