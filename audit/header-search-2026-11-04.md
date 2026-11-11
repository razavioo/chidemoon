# Header search layout

Latest interaction revision: the original search icon stays visible and toggles expansion on repeated clicks. Removed the separate close button and hid the duplicate form submit icon; Enter submits the query. Accessible trigger name reflects open/closed state. Live desktop1440/mobile390 tests pass repeated open/close, absence of close icon, typing and Enter navigation. Backup `/opt/chidemoon/backups/search-toggle-20261104/`; screenshots `search-toggle-*`.

## Inline expansion correction

Replaced the dropdown with expansion from the original icon in the header row. The shell grows from44px to available width capped420px; capacity uses intrinsic menu-item and logo widths rather than the navigation's flexible wrapper. Logo and menu remain visible, and submit icon stays at the original left edge. Mobile fits remaining space (186px at390,116px at320) with shortened placeholder. Tested1440/1100/1024/390/320: same trigger/panel y, no overlap or document overflow; Escape works. Screenshots `header-inline-*` inspected desktop/mobile. Search submit navigation rechecked. This supersedes the dropdown design below.

Removed rules hiding navigation and mobile branding during expanded search. Search now opens beneath its trigger as a 420px panel capped at viewport minus32px, retaining header positions. Search submit icon uses a centered44px grid cell, with a20px SVG; close button occupies a separate44px cell. No nested rounded green submit control. Outside click closes search, alongside existing Escape, trigger toggle and close/focus return.

Published CSS/JS after backing up to `/opt/chidemoon/backups/header-search-20261104/`. Live checks at1440,1100,1024,390: menu and logo visible, brand position unchanged, panel within viewport, SVG vertically centered, focused input, no document overflow, Escape/close/outside click work. Screenshots `output/playwright/header-search-*`; desktop/mobile visually inspected. JS syntax and diff checks pass.
