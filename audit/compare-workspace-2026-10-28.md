# Comparison workspace update — 2026-10-28

Deployed to production. Table removal now deletes matching header/body cells synchronously, updates product count, difference summary, selection storage and URL without navigation or a request. All removal entry points (table, selected chips, bottom bar) share the same behavior in comparison workspace. An eligible single product renders a real full-width table both server-side and after live removal; zero products retains the persistent search.

Restored the picker shortcode as an always-visible search bar. Search uses existing reviewed public catalogue endpoint; selection fetches only the comparison fragment from new GET /chidemoon-core/v1/compare-table?ids=... endpoint. Fragment uses existing eligibility/max-four gates, request throttling, trusted server-rendered facts and responsive image rewriting. Request abort/revision guards prevent superseded responses restoring removed products. Failed additions restore selection to actual displayed columns and retain the existing table. Browser Back requests the matching fragment rather than navigating the document. Share link remains based on displayed columns.

Validation:
- Four products (443/447/853/4539), three consecutive removals: ~4.9/2.8/2.4ms synchronous click handling, one unchanged document, remaining product4539 still in table. These are DOM timings, not end-to-end page speed benchmarks.
- Search چراغ returned results; adding product765 to4539 updated both columns in same document. Clearing all kept search visible; adding from empty recreated a single-product table.
- Reload with ?products=765 retained one real table and search. Mobile390px: table356px within358px wrapper, no document overflow. Screenshot output/playwright/compare-single-search-mobile.png reviewed.
- Simulated fragment network failure retained765 in DOM/storage, cleared busy state and displayed retry message. Successful addition followed by browser Back restored765 without navigation.
- tests/compare-workspace.php passed five read-only integration checks: single table, valid purchase destination, persistent search, rejection of unknown products and unpublished products. PHP lint and JS syntax check passed.

Limit: additions and searches depend on network and server response; deletions do not. No new product data or source content changed.
