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

## Port policy — why Chidemoon split, and what must NOT be ported

Chidemoon was originally a **read-only projection surface of the Kalahamoon
panel**: the WordPress plugin was literally a "catalog projection consumer"
(`Kalahamoon_Catalog_Consumer` + connector client-id/secret + origin
challenge), content launch gates counted products *synced from Kalahamoon*,
and even affiliate linking and schema output branched on that consumer being
enabled. The independent platform inverts this: Chidemoon owns its data,
content, and logic (`chidemoon-core` ships native importer/blocks/forms/
affiliate; `chidemoon-ai` replaces the panel-driven AI studio).

Therefore this directory is **reference-only**. Do not import these into the
active stack; they encode the old consumer protocol and must be rewritten
from scratch if a similar need reappears:

| Legacy piece | Why it must be rewritten, not ported |
|---|---|
| `includes/api/*`, `includes/auth/*` (API client, products, catalog consumer, token store), REST controller | Speaks the Kalahamoon connector protocol; its server side (panel API-key pages) was deleted too. Any future sync = fresh API-to-API design. |
| `deploy/chidemoon/catalog-sync.php` + `mu-plugins/kalahamoon-runtime.php` | Wires the consumer env contract (`KALAHAMOON_CATALOG_CONNECTOR_*`, `KALAHAMOON_INTERNAL_API_URL`). |
| `mu-plugins/chidemoon-launch-readiness.php` | Encodes the old content model (24+ synced products, kalahamoon-fed catalog). New readiness criteria must come from Chidemoon's own editorial model. |
| Plugin affiliate stack (`auto-linker`, `link-cloaker`, `click-tracker`, `price-alert-mailer`) | Gated on the catalog consumer; `chidemoon-core-affiliate` is the native replacement. |
| `theme/chidemoon-theme/` (June-era block theme) | Superseded by `themes/chidemoon-blocksy-child`. |
| `scripts/deploy-chidemoon-vps.sh` (+ test) | Superseded by this repo's `ops/deploy-release-bundle.sh`. |

Safe to consult (generic, no consumer coupling): neutral content blocks
(faq, pros-cons, testimonials, cta, rating-box...), i18n/RTL helpers,
fa_IR translations.

## Operational loose end: Discourse

`community.chidemoon.com` still runs from compose files that only exist on
the VPS inside the retired kalahamoon deployment. Its deploy assets here
(`chidemoon/deploy/compose.discourse.yml`, `deploy-community.sh`,
`Caddyfile.community.example`) are snapshots, not a maintained pipeline.
Decide explicitly: promote Discourse ops into this repo as first-class
tooling, or decommission it.
