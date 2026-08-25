# Chidemoon WordPress implementation

Chidemoon is the Persian-first editorial and affiliate surface for the Kalahamoon product catalog. It uses a native WordPress block theme plus the reusable Kalahamoon WordPress plugin; no site-specific feature plugin is part of the runtime.

## Source-of-truth boundaries

- Kalahamoon remains authoritative for tenant identity, verified products, publication review, category intelligence, comparison types, listings, prices, and lead delivery.
- Chidemoon owns public editorial posts, pages, layout, navigation, affiliate disclosures, reviewed Shop-the-Look stories, and presentation preferences.
- Chidemoon forces catalog authority to `remote`. A local WordPress product may be previewed by an editor, but it cannot shadow or enter the public Chidemoon catalog.
- Product synchronization is idempotent. Remote cache records may be removed only after a complete authoritative sync; locally authored records are never deleted by remote reconciliation.
- Public cards fail closed: only active, verified, complete products with a recent successful source timestamp can render. Prices older than 24 hours are hidden; products older than 72 hours are hidden.

## Repository layout

- `theme/chidemoon-theme/` — Full Site Editing theme, templates, patterns, styles, and lightweight interactions.
- `deploy/` — reproducible package builder and Discourse deployment definitions.
- `packages/` — generated checksummed release artifacts. Do not edit generated ZIP files manually.
- `../apps/wordpress-plugin/kalahamoon/` — the reusable catalog, affiliate, AI, form, alert, and block implementation.
- `../deploy/chidemoon/mu-plugins/` — runtime authority, analytics, content migration, and launch-readiness controls.

## Authentication and connection setup

Use the Kalahamoon plugin's standard OAuth connection flow. Do not copy tenant API credentials into theme code, MU plugins, content, JavaScript, or release packages. The internal API URL is host-managed configuration; OAuth tokens remain in the plugin's protected WordPress settings.

## Build and local verification

Build artifacts from the repository root:

```bash
./chidemoon/deploy/build-packages.sh
```

The builder creates:

- `chidemoon/packages/chidemoon-theme.zip`
- `chidemoon/packages/kalahamoon.zip`
- `chidemoon/packages/manifest.json`
- `chidemoon/packages/manifest.sha256`
- `chidemoon/packages/installed-files.sha256`

It normalizes archive timestamps and ordering, then records both package and installed-file checksums. The Kalahamoon plugin package omits development dependencies and tests.

Run the focused contracts before creating a sealed release:

```bash
apps/wordpress-plugin/kalahamoon/vendor/bin/phpunit \
  -c apps/wordpress-plugin/kalahamoon/phpunit.xml
node_modules/.bin/vitest run chidemoon/deploy/kalahamoon-runtime.test.ts
```

## Editorial workflow

Every public editorial post must have:

- a real featured image with meaningful alternative text;
- a concise excerpt, usable heading hierarchy, and a source link;
- a content type in `chidemoon_content_type`;
- editor, review timestamp, and source-check timestamp metadata;
- an affiliate disclosure whenever commerce blocks are present;
- at least 800 words for a guide and 450 words for other reviewed articles.

Shop-the-Look stories use the reusable `kalahamoon/shop-the-look` block. A launch-ready look contains at least three hotspots linked to products that pass the current public catalog policy. Comparisons require two to four products from one comparison type.

AI output is an editor draft with locale and provenance. It must never publish automatically. Editors remain responsible for factual review, source checking, product freshness, image rights, alt text, and final publication.

## Launch-readiness gate

Inspect the current report with WP-CLI:

```bash
wp chidemoon launch-readiness --allow-root
```

The production gate requires all of the following from real data:

- 24 launch-ready products across at least four categories;
- two comparison types with at least three compatible products each;
- six reviewed Shop-the-Look stories with at least three valid hotspots each;
- 12 fully reviewed editorial posts, including six guides;
- a live community with eight topics, two categories, and three recently active topics with replies;
- Persian locale, Tehran timezone, remote catalog authority, closed comments, and configured Matomo;
- every required public route published.

Use `--require-ready` to return a non-zero exit code when any gate fails. Missing data is a release blocker; do not create sample products, fake reviews, placeholder conversations, or fabricated editorial signoff to satisfy it.

## Production deployment

Chidemoon deploys only from a promoted, sealed Kalahamoon release:

```bash
./scripts/deploy-chidemoon-vps.sh
```

The full lane verifies the release bundle and package checksums, validates current WordPress service state, installs the packages, runs the classified one-way content migration, evaluates launch readiness, and observes service/public-route stability for 15 minutes.

`CHIDEMOON_REQUIRE_LAUNCH_READY=true` is the production default. Setting it to `false` is only for an explicitly labeled non-launch candidate; it must not be used to claim that Chidemoon is publish-ready.

Pure Chidemoon theme or site-content work is excluded from the Kalahamoon release center. Changes under `apps/wordpress-plugin/kalahamoon/**` are a Kalahamoon product change and require the WordPress plugin version/release workflow before deployment.

## Required acceptance evidence

Before public launch, verify the public and authenticated-editor paths separately:

- home, shop, Shop-the-Look, compare, guides, magazine, forms, community handoff, and 404;
- desktop plus 320, 360, 390, 768, 1024, and 1280 pixel viewports;
- keyboard focus, menu trapping/return focus, filters, quick view, favorites, same-type comparison, form validation/success/error, and AI no-result/rate-limit states;
- one H1, Persian locale, canonical/title/description, Article schema where accurate, image alt/dimensions, no horizontal overflow, no console errors, and no broken images;
- source API response, persisted WordPress cache, launch-readiness report, public render, analytics event receipt, and the guarded deployment health window.

An HTTP 200, a successful package install, or an empty green test suite alone is not launch proof.
