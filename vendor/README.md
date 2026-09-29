# Offline third-party packages

Production hosts have no package-registry access, so these reviewed archives
are tracked intentionally (exception to the "no binaries in git" rule) and
are checksum-verified by `ops/create-release-bundle.sh` before every release:

| Archive | Checksum file | Purpose |
|---|---|---|
| `hello-elementor.zip` | `hello-elementor.sha256` | Hello Elementor 3.5.1, the Elementor Theme Builder host |
| `woocommerce.zip` | `woocommerce.sha256` | Shop engine required by `plugins/chidemoon-core` |

To upgrade: replace the `.zip`, regenerate its `.sha256` with
`sha256sum <file>.zip > <file>.sha256`, and commit both together.
Never commit an unverified archive.

The old Blocksy archive remains a historical source artifact. It is excluded
from new release bundles, container mounts, and initialization.
