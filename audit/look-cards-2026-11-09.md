# Look gallery cards — 2026-11-09

Published a gallery-scoped editorial card design on /shop-the-look/:
two columns on wide screens, one column on small phones and narrow tablet layouts,
rounded image frames, compact title headers, available taxonomy chips, unique
linked-product counts and contextual hotspot guidance. Static inspiration scenes
receive a neutral caption instead of an instruction to tap nonexistent hotspots.

Images retain natural proportions; source hotspot coordinates and homepage hero
selection/layout are unchanged by this update. Existing uncommitted edits retained.

Validation: production PHP lint passed. Playwright checked 1280, 768, 390 and 320px;
no horizontal overflow. Seven scenes render. Mobile hotspot opens its product panel
and product link. Desktop and mobile screenshots are in output/looks-after-*.png.
Original production PHP/CSS copies saved at /tmp/looks-before-cards.php and
/tmp/styles-before-cards.css on the host. Public page cache invalidated after deploy.
