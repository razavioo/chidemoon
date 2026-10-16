import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

for (const script of ['source-import-recovery.php', 'catalog-sync-recovery.php']) {
  test(`source sync regression: ${script}`, () => {
    const result = spawnSync('php', [fileURLToPath(new URL(script, import.meta.url))], {
      encoding: 'utf8', timeout: 30_000,
    });
    assert.ifError(result.error);
    assert.equal(result.status, 0, result.stdout + result.stderr);
  });
}
