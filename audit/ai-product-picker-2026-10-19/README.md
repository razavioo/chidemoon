# AI Look product selector — 2026-10-19

Replaced manual numeric ID input in Look Studio with inline name/local-product-URL search, thumbnail results, add/remove controls and a visible selected-product list (1–6 products). Internal IDs remain hidden and feed the original validated generation API. Enter in search does not submit a paid generation job. Empty selection blocked with explanatory text. Debounce, cancellation, stale-result protection, duplicate prevention and max-six UI included.

Added authenticated GET /chidemoon-ai/v1/products with GENERATE capability and per-product edit permission checks. Product URL resolution is restricted to the site hostname, preserves percent-encoded Persian slugs and rejects non-product pages. No outgoing URL fetches. Cache-busted admin JS and new scoped admin CSS.

PHP lint, JS syntax and git diff --check passed. Real authenticated browser: name search found products; selected by name, added product 794 by site URL, removed prior selection successfully; selected six products and all further add controls disabled. Verified Persian labels after deployment. API regression verifies anonymous 401, canonical product link resolves 794, external URL and non-product page return empty. No AI jobs created and no paid generation called. Temporary browser diagnostic session revoked; local authentication artifacts cleared.

Production pre-change PHP/JS in /opt/chidemoon/backups/ai-picker-20261019. Screenshot artifacts in output/playwright/ai-*picker*.png.
