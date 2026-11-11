# Shop the Look hero and hotspot fix — 2026-11-09

- The homepage hero now stores a fixed editorial list in `chidemoon_home_hero_look_ids`. The live list is `[8608, 8607, 3979]`, exactly three Looks, each with approved product hotspots.
- New published Looks continue to appear in the full `/shop-the-look/` page and the homepage Shop the Look strip. They do not alter the hero list.
- Removed the fallback inspiration card from the hero, which was the final image without hotspots.
- Homepage render audit: hero 3 cards / 12 hotspots; Shop the Look strip 4 cards / 17 hotspots.
- Hotspot coordinates now use the image's full box (`object-fit: fill`) instead of a cropped cover box. Cropping was the source of the visible offset between a point and its product. The 44px touch targets remain on small screens.
- Browser checks at 320, 390, 768 and 1440px confirmed exactly three hero cards and no new fourth hero image. Screenshots and geometry data are in `output/playwright/home-looks-*.png` and the Playwright run output. Desktop hero and strip had no horizontal overflow; the strip intentionally scrolls horizontally on smaller screens.
- PHP syntax was checked inside the production WordPress container through the existing audit script; repository `git diff --check` passes.
