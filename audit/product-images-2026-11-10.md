# Product image audit — 2026-11-10

Checked all 780 non-trashed WooCommerce products: 718 published, 62 draft.
Validated 5,566 referenced original attachment files with getimagesize and all
registered thumbnail sizes on disk. No missing or invalid image files found.
Checked 46,550 unique original/thumbnail/responsive URLs by HTTP: all returned 200.

Found and fixed one missing featured image: draft product 739 (بانکه سرامیکی
جاادویه سرویس پاسماوری) had its valid image 740 in the gallery only. Assigned it
as the featured image and removed the duplicate gallery entry. Draft status was
preserved; previous assignments backed up in
_chidemoon_before_image_audit_20261110.

Compared recorded source image URLs with imported attachment source metadata.
Two apparent incomplete galleries were actually duplicate image sizes in the
source list. After canonicalizing Basalam resize suffixes, all recorded unique
source photos were represented; no missing source photos needed downloading.

Final independent check: all 780 products have valid, renderable featured images.
Playwright checked shop and product pages 6483/8625 at 390 and 1440px, scrolling
images into view: no failed image loads. This does not reproduce the previously
reported intermittent admin/import UI issue or establish its cause.

Evidence: output/all-product-images-audit.json (before repair),
output/product-image-http-results.json, output/image-audit-unimported.json.
