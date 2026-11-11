# Product search redesign

Applied to production at the user's request. Search now queries products only, including old `content_type=post` URLs. Removed the All / Products / Articles tabs and replaced them with a product count. Updated native Elementor template copy (404 and 406), with original data saved in `_ch_search_product_copy_backup`.

Search results use independent image-led cards, rounded square image areas, small category labels, clear prices, and accessible product links. Desktop has four columns, tablet three, and narrow mobile two. Editorial archives remain separate.

Browser verification at 320, 390, 768 and 1440 pixels: three expected macrame-mirror results, no horizontal overflow, no article tabs. Legacy article-filter URL returned product cards and zero article results. Checked product navigation, no-result state and pagination. PHP syntax check passed. Screenshots: `output/playwright/search-redesign-*.png` and `search-redesign-final-mobile.png`.
