# Toman accounting and display correction

Owner confirmed that Chidemoon should use toman. Set WooCommerce accounting currency to IRT and registered its currency name. Converted all 532 verified source-derived IRR product amounts to IRT by exact decimal division by ten, preserving source observations in their original IRR unit. Existing importer conversion already supports IRR → IRT, so future source updates retain the correct scale. Cleared product price caches. Sidebar filters, stepper hints and price alerts use the configured currency and now show toman.

The two manually entered Digikala prices (443: 1,195,000; 447: 749,000) were already toman according to the owner's clarification and were preserved. Source-backed amount validation, source identity checks, importer lock and database transaction guarded the migration. All 532 persisted amounts and rendered currency labels passed verification; product 6653 now displays 3,150,000 toman.

Pre-migration SQL backup and operation result: `/opt/chidemoon/backups/toman-20261022/`. `result.json` contains live verification. Earlier price audit documents the historical IRR configuration and has been superseded by this currency migration.
