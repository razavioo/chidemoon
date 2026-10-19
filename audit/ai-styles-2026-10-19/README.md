# AI Look styles and reference images — 2026-10-19

Expanded styles from 4 to 21 with shared Persian labels and English generation descriptions. The same catalog now drives UI options, REST validation and prompt construction, preserving all four existing keys. Added modern, contemporary, classic, neoclassical, boho, Japandi, industrial, rustic, farmhouse, traditional Persian, Persian-modern fusion, Mediterranean, coastal, mid-century, Art Deco, eclectic and wabi-sabi.

Replaced Look Studio reference attachment ID field with WordPress native media picker (image-only library/upload), optional up to two images, thumbnail/name previews and removal. Reopening preserves selection. Over-limit selection reports an error and retains prior accepted images. Existing hidden IDs feed unchanged server validation. Included plain Persian explanation of reference purpose.

Production original PHP/JS in /opt/chidemoon/backups/ai-styles-20261019. PHP lints, JS syntax, git diff --check pass. Server verify.php confirms 21 distinct prompt descriptions. Authenticated browser verifies actual media dialog, two previews and correct payload IDs, removal, selection restoration, three-image rejection. No AI jobs created. Temporary diagnostic session revoked and local credential artifacts cleared. Screenshot output/playwright/ai-styles-reference-picker.png.
