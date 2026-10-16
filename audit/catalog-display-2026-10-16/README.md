# Catalog filter and price display repair — 2026-10-16

- Root cause: all 389 published products had numeric imported WooCommerce prices, but all source observations were older than 24 hours. Commercial::price_html replaced every amount with “Current price unavailable”.
- Preserve the imported numeric amount for older observations and label it “آخرین قیمت ثبت‌شده”; retain unknown treatment when no amount exists and native Woo price formatting for fresh prices. Do not fabricate source timestamps or expose historical sale discounts as current.
- Disable the catalog filter list by default and explicitly turn it off in archive document 410. Count and ordering controls retained. Elementor document saved through its native API; original data backed up in _chidemoon_before_filter_removal_20261016.
- Production originals: /opt/chidemoon/backups/catalog-display-20261016/. Compared production against local before deploying only the two modified files; no unrelated changes replaced.
- Isolated regression passed for recorded, fresh, missing and zero prices. Full installed Elementor acceptance checks passed.
- Actual browser: shop has 0 .ch-catalog-filters and 16 recorded-price blocks on the first page. Mobile viewport 390×844 has no horizontal overflow. Product 1014 shows 8,400,000 IRR and recorded-price label; no horizontal overflow. Screenshot: output/playwright/shop-prices-mobile.png.
- All 389 published products have numeric prices and recorded-price labels. No source observation times or upstream prices changed.
