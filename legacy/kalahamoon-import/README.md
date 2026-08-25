# Legacy Kalahamoon Import

Assets extracted from the Kalahamoon monorepo (`github.com/razavioo/kalahamoon`)
when Chidemoon became an independent platform. Nothing in this directory is
referenced by the active Chidemoon stack (`compose.yml`, `plugins/`, `themes/`,
`ops/`); it is kept for reference, recovery, and future porting.

## Contents

| Path | Origin | Notes |
|------|--------|-------|
| `apps/wordpress-plugin/kalahamoon/` | kalahamoon worktree at v2.11.0 (`d8e67be0`) | The "kalahamoon" WordPress plugin as deployed on the retired embedded chidemoon.com stack (plugin v1.x line). Superseded by `plugins/chidemoon-core` + `plugins/chidemoon-ai`. |
| `chidemoon/` | git history `10d89f6f^` (last state before removal on 2026-08-25) | Original June-2026 layout: `theme/chidemoone-theme` block theme source (incl. YekanBakh fonts), `deploy/build-packages.sh`, Discourse deploy assets, local dev compose/init scripts. |
| `deploy/chidemoon/` | git history `10d89f6f^` | Sealed-release install assets: `install.sh`, mu-plugins (`kalahamoon-runtime.php`, `chidemoon-launch-readiness.php`), `catalog-sync.php`, `compose.staging.yml`. |
| `scripts/deploy-chidemoon-vps.sh` (+ `.test.ts`) | git history `10d89f6f^` | The guarded VPS deploy script used for chidemoon releases from inside kalahamoon bundles. Replaced by this repo's `ops/deploy-release-bundle.sh`. |

## Provenance commands

Re-export any of these from the kalahamoon repository with:

```bash
git -C <kalahamoon> archive --output=/tmp/export.tar 10d89f6f^ -- \
  chidemoon deploy/chidemoon \
  scripts/deploy-chidemoon-vps.sh scripts/deploy-chidemoon-vps.test.ts
```

## What was intentionally NOT moved

- Release-product plumbing entries (`WORDPRESS_PLUGIN`) that remain in
  kalahamoon's changelog system so historical release notes keep rendering.
- The `wordpress-plugin` client-compatibility contract in
  `src/lib/extension/client-version.ts`: already-installed copies of this
  plugin still call the live panel API with that client type.
