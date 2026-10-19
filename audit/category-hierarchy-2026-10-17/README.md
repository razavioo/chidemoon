# Category hierarchy — 2026-10-17

- Heater term 92 remains directly under heating appliances 91.
- Machine-made carpet 115 and handmade carpet 165 moved directly under carpets/textiles 113. Term 114 renamed to زیرانداز, retaining its slug and product assignments.
- Header category template uses a dynamic hierarchical category shortcode (native Elementor shortcode widget), replacing the alphabetical flat Loop Grid. Child lists are indented; existing compact text-only links retained. Generator updated for future templates.
- Original PHP/CSS saved in /opt/chidemoon/backups/category-hierarchy-20261017. Original term names/parents saved in _chidemoon_hierarchy_before_20261017_{id}; original Elementor data in _chidemoon_hierarchy_before_20261017 metadata.
- PHP lint and git diff --check passed. Live browser snapshot confirms ancestry; desktop/mobile screenshots in output/playwright/category-hierarchy-*.png. Old machine carpet category URL still returns HTTP 200.
- operation.php is a reviewed one-time operation, not a migration; inspect existing template and taxonomy before reusing.
