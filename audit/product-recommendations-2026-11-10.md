# Product recommendations — 2026-11-10

Replaced the product-page structural category/text-link block with two product-card
rows at the end of main content: similar products and complementary products.
Removed the old related-product loop widget from Elementor template 408 to avoid
duplicate suggestions. Original template data is backed up in
_chidemoon_before_recommendations_20261110; category archive structural links remain.

Similar candidates come from the current product's most specific categories.
Complementary candidates use explicit category relationships (including legacy
categories), covering every one of the 718 published products. Coffee equipment
prioritizes cups/mugs/glasses and serving trays; bedroom-lighting suggestions filter
out unrelated standing lamps. Matching style, room, color and material improves
ranking. Categories alternate to provide variety rather than a single category
dominating. The current product and overlapping candidates are excluded.

Only published, catalog-visible, approved, currently available products with images
can appear. Each row has up to six cards. A category with insufficient available
alternatives displays an honest empty state instead of unrelated products.

Validation: PHP lint passed. Production selection checks covered coffee makers,
espresso makers, curtains, sofas, mirrors, bedspreads, tea sets and heaters: no
duplicates, self references, invisible or unavailable candidates. Coverage check
confirmed all published products have complementary-category rules. Playwright
verified both six-card rows, removal of the old blocks, image loads and no page
overflow at 390/1440px. Mobile screenshot visually reviewed. Rows scroll horizontally;
long titles clamp to three lines. Public cache invalidated after deployment.

## Comparison recommendations — 2026-11-10
Added similar-product cards and relevant comparison-article cards below the comparison table, including AJAX refresh after removing a selected product. Selected products and duplicate suggestions are excluded. Existing editorial comparisons have explicit reviewed category mappings; future articles referencing at least two products in the selected categories qualify automatically. No unrelated fallback articles.

Live browser validation: table selection 8625/8647 returns six alternatives and the relevant table comparison article. Lamp selection 443/447 returns alternatives and an honest empty article state. Removing a product refreshes the recommendation selection; removing the last product removes recommendations. Verified document width equals viewport at 320, 390, 768 and 1440px after fixing the recommendation row positioning context. PHP lint and JavaScript syntax checks passed. Existing compare-shared-link unit tests cannot execute successfully with their outdated DOM mocks (missing browser APIs); actual browser interaction verified instead.
