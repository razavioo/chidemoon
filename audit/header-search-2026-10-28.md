# Header search and single purchase row

Follow-up: search now expands horizontally from the original 44px header button in the same header row, with 220ms width transition and reduced-motion support. Desktop expands to420px; mobile390px expands to290px beside menu. Header height remains76px desktop and64px mobile, panel closes to44px. Original navigation/brand return on close; closed input is inert and aria-hidden. Browser checked open/close geometry and no horizontal overflow; screenshot output/playwright/header-inline-mobile.png. This replaces the full-width second-row behavior described below.

Deployed 2026-10-28. Removed JS-cloned purchase shortcuts from comparison header; original final purchase row retained. Browser checked four selected products: zero header purchase buttons, four footer purchase buttons, identical Y coordinates on mobile.

Replaced modal dialog search with a hidden, expandable header-flow panel on desktop and mobile. Toggle exposes aria-expanded and aria-controls; opening focuses input, Escape/close returns focus to toggle. No modal, backdrop, scroll lock or search placement in navigation drawer. Native full-page GET navigation uses location.assign, preserving previous page in browser history; restored pages close the panel via pageshow/pagehide handlers.

Browser verified /shop/ -> search for چراغ -> actual results page -> browser Back returns /shop/ with closed panel. Desktop input lies inside expanded header (y87 to131 in header y3 to159); mobile input y81 to125, panel358px at390px viewport, zero document overflow. Screenshot output/playwright/header-search-mobile.png. JS syntax and git whitespace checks passed.
