# Storefront repairs — 2026-10-16

Current production behavior:

- 389 reviewed products are public; missing-source records remain drafts and the duplicate is recoverably archived.
- Catalog synchronization is operational; renew access credentials before acknowledging long imports.
- Related-product queries guard against Elementor reentrancy to prevent product-page crashes.
- Recorded prices remain visible and labeled. Per the explicit store-owner instruction, the existing amount is followed by تومان without numeric conversion; internal source currencies and amounts are preserved.
- Product category hierarchy and five incorrect product assignments repaired. Header categories use compact product-category text links without article topics, images or action buttons.
- Product archive has a right sidebar containing only price and category filters, with native query/pagination. Product-card actions align across each row.
- Header normal navigation color is shared; active/hover color is #41734C. Active navigation is recomputed from the current URL to prevent Elementor's cached header from retaining another page's selection. Verified products active on /shop/ and guides active on /guides/ in desktop/mobile layouts.

WordPress data changes are documented by the operation and verification scripts in:

- publication-2026-10-16/
- catalog-display-2026-10-16/
- category-repair-2026-10-16/
- catalog-sidebar-2026-10-16/

These scripts record the completed maintenance operations; review their preconditions and backups before reuse. They are not automatic migrations. Database/media backups and original production files are retained on the server. Runtime logs, screenshots and browser session artifacts are not part of this commit.

Validation completed during the repairs: 46 Node checks, related-query and token-expiry PHP regressions, recorded-price cases, installed Elementor acceptance, live product readiness, numeric price preservation and browser filtering/pagination/layout checks. The header follow-up passed JavaScript syntax and actual browser checks; normal navigation is rgb(23,36,31), selected navigation rgb(65,115,76).
