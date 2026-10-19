# Look image and hotspot UI optimization — 2026-10-19

Baseline: source scene images 394,464 / 767,543 / 307,854 / 287,672 bytes. Generated additive WebP derivatives at 480, 768 and up to 1280px (quality 80), retaining source attachments and metadata. Variant URLs/sizes recorded in _chidemoon_look_webp. Reviewed images.php is a manual derivative generator for the four current attachments; future uploads without variants retain responsive WordPress image fallback.

Gallery renders picture/source WebP with responsive sizes, original image fallback, explicit width/height, first scene eager/high priority and subsequent scenes native lazy loading. Inspiration images use the same optimized image path. Browser may prefetch nearby lazy images according to its own distance threshold. Removed unused vendor stylesheet from the public custom gallery only; kept editor-preview/other-page compatibility path.

Full-width largest WebP variants total 484,828 bytes versus original 1,757,533 bytes (72.4% smaller). Fresh 390px / DPR1 browser chooses 480px versions: 25,950 / 40,354 / 21,862 / 19,004 bytes; no REYCOB frontend assets requested. These are image byte savings, not a measured overall page speed percentage.

Popover UI: desktop 320px, mobile 296px maximum; box-sizing, padding 16/12px, gaps 12/10px, thumbnails 72/60px, title typography and full-width 44px CTA aligned. Scoped green active state avoids theme pink focus background. Existing interaction/positioning preserved.

Production originals in /opt/chidemoon/backups/looks-opt-20261019. PHP lint and git diff --check passed. Live desktop all six hover cards stay accessible at CTA; mobile all six open within viewport with no horizontal overflow. Keyboard Tab reaches CTA and Escape closes. Visual screenshot output/playwright/looks-optimized-mobile.png.
