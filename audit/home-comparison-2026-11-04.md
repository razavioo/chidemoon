# Homepage calls to action and editorial comparison

Published hero image CTA removal (hotspot product links retained), a native Elementor heading/action row for the shoppable section, and a new third homepage section with a curated comparison of products 451/455. The comparison uses recorded merchant facts and practical use-case analysis, explicitly distinguished from hands-on testing. Product thumbnails and links are live WooCommerce data; eligibility gates prevent exposing unpublished/ineligible products. Existing sections remain after the inserted comparison.

Migration `tools/home-comparison-section.php apply` saves native Elementor content with `_chidemoon_before_home_comparison_20261104` backup. Code backup: `/opt/chidemoon/backups/home-comparison-20261104/`.

Live browser checks at 1440 and 390px: no hero footer links, action to the left of its section heading, comparison third in section order, two product columns and six body rows, no horizontal document overflow. Visually inspected both breakpoints and corrected comparison section maximum width to share homepage gutters. PHP lint passed for modified classes. Screenshots: `output/playwright/home-header-comparison-{1440,390}.png` and `home-comparison-{1440,390}.png`.
