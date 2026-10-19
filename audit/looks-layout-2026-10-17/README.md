# Shop the Look page layout — 2026-10-17

- Migrated legacy scene 594 to published REYCOB scene 3979 using the original image 462 and both approved product coordinates (451 at 46/24; 455 at 80/22). Original record and relationships retained; migration link prevents double display.
- Added dynamic chidemoon_look_gallery shortcode to Core. Renders published REYCOB scenes and remaining native inspiration Looks sequentially, filters both by chidemoon_room. Registers the room taxonomy for vendor scene records.
- Rebuilt Elementor page 62 with existing heading and one room filter at the top, then the gallery widget. Removed duplicate room navigation, listing heading/context and old article-card grid. The two inspiration images remain; no unsupported hotspots added.
- Assigned new scene 3976 to bedroom and migrated scene 3979 to reading-corner. Both scenes use the existing native product Loop Item instead of article template 505. New REYCOB scenes automatically enter the gallery on publication.
- Original Elementor tree backed up in _chidemoon_before_looks_layout_20261017; prior card settings in _chidemoon_before_gallery_card_20261017. Original Core PHP/CSS under /opt/chidemoon/backups/looks-layout-20261017.
- PHP lint and git diff --check pass. Public live browser: one room filter; four scenes in one vertical stack; two and four vendor hotspots. All six select and align the correct product slide. Bedroom filter returns only 3976; reading-corner returns 3979 and inspiration 588. Mobile 390px has no horizontal overflow. Screenshots in output/playwright/looks-layout-*.png.
- operation.php is a reviewed one-time production content operation, not a general migration. Inspect current page content before reusing.

## Follow-up: hotspot-only product details

Removed below-image product carousels from the gallery output entirely. The gallery now renders image/points directly from existing REYCOB image, product and coordinate metadata; vendor editor records remain the source. Each point opens a separate interactive popover with thumbnail, name, price and a genuine product-detail link (no interactive link nested in a button).

Added pointer hover, focus, click/touch, Escape and outside-click behavior to public-design.js, including Elementor DOM refresh support. Popovers clamp horizontally and choose above/below placement without covering their own trigger on small images. Core originals backed up in /opt/chidemoon/backups/looks-popovers-20261017.

PHP lint, JS syntax and git diff --check passed. Live browser: zero below-image cards, all six desktop hover popovers stay open while moving onto their CTA; all six mobile popovers visible and inside viewport, no horizontal overflow. Tab reaches the CTA; Escape closes. Actual CTA click navigates to the matching product page. Screenshot: output/playwright/looks-hotspot-cta-mobile.png.
