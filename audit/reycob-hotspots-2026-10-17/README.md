# REYCOB Elementor hotspot compatibility — 2026-10-17

REYCOB Shop the Look 1.4.1 queues frontend.css only inside render_shortcode, after wp_head has printed styles. Confirmed with scene 3976 (four saved points) through a read-only WordPress head/shortcode/footer rendering: stylesheet absent from head before fix, present afterward. User reports shortcode widget in Elementor editor preview; saved occurrence is an autosave revision, not the public page.

Core now enqueues the vendor stylesheet before head output when the vendor plugin is active. No vendor files or user Elementor documents modified. Scoped CSS makes the carousel track LTR while retaining RTL slide content, matching the vendor script's negative horizontal translation.

Deployed Core PHP/CSS. Originals in /opt/chidemoon/backups/reycob-hotspots-20261017. PHP lint and git diff --check passed. Browser verification used locally served HTML rendered by the production WordPress installation with original scene/products/assets, not an authenticated editor session. All four points position absolutely inside the image on desktop; selecting every point aligns its matching slide on desktop and mobile 390px. No mobile horizontal overflow. Editor refresh is required to load the corrected head styles.
