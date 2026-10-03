import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, copyFileSync, symlinkSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

test('scheduled host tasks resolve secrets outside immutable releases and respect explicit overrides', () => {
  const fixture = mkdtempSync(join(tmpdir(), 'chidemoon-host-ops-'));
  try {
    const bin = join(fixture, 'bin');
    mkdirSync(bin);
    writeFileSync(join(bin, 'docker'), '#!/bin/sh\nprintf "%s\\n" "$@"\n', { mode: 0o755 });
    const deployment = join(fixture, 'deployment');
    const release = join(deployment, 'releases', 'reviewed-release');
    const local = join(fixture, 'local');
    for (const root of [release, local]) mkdirSync(join(root, 'ops'), { recursive: true });
    writeFileSync(join(deployment, '.env'), 'fixture only\n');
    writeFileSync(join(local, '.env'), 'fixture only\n');
    symlinkSync(release, join(deployment, 'current'));
    for (const service of ['scheduler', 'backup']) {
      const script = `${service}-host.sh`;
      for (const root of [release, local]) copyFileSync(resolve('ops', script), join(root, 'ops', script));
      for (const [root, override, expected] of [
        [join(deployment, 'current'), '', join(deployment, '.env')],
        [local, '', join(local, '.env')],
        [join(deployment, 'current'), join(fixture, 'explicit.env'), join(fixture, 'explicit.env')],
      ]) {
        const result = spawnSync('bash', [join(root, 'ops', script)], {
          encoding: 'utf8', env: { ...process.env, PATH: `${bin}:${process.env.PATH}`, CHIDEMOON_ENV_FILE: override },
        });
        assert.equal(result.status, 0, result.stderr);
        const args = result.stdout.trim().split('\n');
        assert.equal(args[args.indexOf('--env-file') + 1], expected);
        assert.equal(args[args.indexOf('-f') + 1], join(root === local ? local : release, 'compose.yml'));
        assert.equal(args.at(-1), service);
      }
    }
  } finally { rmSync(fixture, { recursive: true, force: true }); }
});
