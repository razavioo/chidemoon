# Offline third-party packages

Production hosts have no package-registry access, so these reviewed archives
are tracked intentionally (exception to the "no binaries in git" rule) and
are checksum-verified by `ops/create-release-bundle.sh` before every release:

| Archive | Checksum file | Purpose |
|---|---|---|
| `blocksy.zip` | `blocksy.sha256` | Parent theme for `themes/chidemoon-blocksy-child` |
| `woocommerce.zip` | `woocommerce.sha256` | Shop engine required by `plugins/chidemoon-core` |

To upgrade: replace the `.zip`, regenerate its `.sha256` with
`sha256sum <file>.zip > <file>.sha256`, and commit both together.
Never commit an unverified archive.
