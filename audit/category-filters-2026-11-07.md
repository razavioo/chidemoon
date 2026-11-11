# Category-aware filters and search inset — 2026-11-07

Published category-scoped product facets. Options come from published, reviewed external products in the current category and its descendants, independently of the active selections. Category family profiles prioritize relevant axes and remove room/style axes from appliances. Missing values and single-option facets are hidden. Categories without classified variation retain price. The general shop keeps its broad discovery filters.

Expanded RTL header search uses 16px inline-start and 12px inline-end padding, verified by computed styles at desktop and 390px mobile with no document overflow. Screenshots in output/playwright/search-padding-20261107-{desktop,mobile}.png.

Validation: PHP lint passes. An integration audit covered 61 populated category scopes and found zero options outside their category product set (output/category-facets-20261107.json; rerun output/category-facets-final-20261107.txt). Live mobile espresso-maker form submission retained the selected material, returned 11 matching cards and kept the relevant facet options. Screenshot: output/playwright/category-filter-applied-20261107.png.

Production backup: /opt/chidemoon/backups/category-filters-20261107/. No product specifications were inferred or seeded.