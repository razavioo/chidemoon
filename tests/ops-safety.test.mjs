import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { existsSync, mkdtempSync, mkdirSync, readFileSync, readdirSync, realpathSync, rmSync, statSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { gunzipSync } from 'node:zlib';
import { test } from 'node:test';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

test('a failed database dump cannot publish a gzip backup; verified pairs retain original data', () => {
  const scratch = mkdtempSync(join(tmpdir(), 'chidemoon-backup-'));
  try {
    const bin = join(scratch, 'bin');
    const backups = join(scratch, 'backups');
    const uploads = join(scratch, 'uploads');
    for (const path of [bin, backups, uploads]) mkdirSync(path);
    writeFileSync(join(uploads, 'editorial.txt'), 'محتوای واقعی آزمایشی');
    const sql = '-- MariaDB dump\nCREATE TABLE `wp_posts` (`ID` bigint);\nINSERT INTO `wp_posts` VALUES (42);\n-- Dump completed on 2026-01-01\n';
    writeFileSync(join(scratch, 'fixture.sql'), sql);
    writeFileSync(join(bin, 'date'), '#!/bin/sh\nprintf "20260101T120000Z\\n"\n', { mode: 0o755 });
    writeFileSync(join(bin, 'mariadb-dump'), '#!/bin/sh\ncat "$CHIDEMOON_FIXTURE_SQL"\nexit "${CHIDEMOON_FIXTURE_DUMP_EXIT:-0}"\n', { mode: 0o755 });
    const script = join(scratch, 'run-backup.sh');
    writeFileSync(script, readFileSync(join(root, 'ops/run-backup.sh'), 'utf8').replaceAll('/backups', backups).replace('tar -C /uploads', `tar -C ${uploads}`));
    const env = {
      ...process.env,
      PATH: `${bin}:${process.env.PATH}`,
      MARIADB_DATABASE: 'disposable_fixture',
      MARIADB_USER: 'fixture',
      MARIADB_PASSWORD: 'local-fixture',
      CHIDEMOON_BACKUP_RETENTION_DAYS: '14',
      CHIDEMOON_FIXTURE_SQL: join(scratch, 'fixture.sql'),
    };
    const failed = spawnSync('sh', [script], { env: { ...env, CHIDEMOON_FIXTURE_DUMP_EXIT: '7' }, encoding: 'utf8' });
    assert.notEqual(failed.status, 0, failed.stderr);
    assert.deepEqual(readdirSync(backups), [], 'Failed partial SQL must leave no published or temporary backup.');

    const passed = spawnSync('sh', [script], { env, encoding: 'utf8' });
    assert.equal(passed.status, 0, passed.stderr);
    const files = readdirSync(backups).sort();
    assert.deepEqual(files, ['checksums-20260101T120000Z.sha256', 'database-20260101T120000Z.sql.gz', 'uploads-20260101T120000Z.tar.gz']);
    assert.equal(gunzipSync(readFileSync(join(backups, files[1]))).toString(), sql);
    for (const line of readFileSync(join(backups, files[0]), 'utf8').trim().split('\n')) {
      const [digest, name] = line.split('  ');
      assert.equal(createHash('sha256').update(readFileSync(join(backups, name))).digest('hex'), digest);
    }
    const extracted = spawnSync('tar', ['-xOzf', join(backups, files[2]), './editorial.txt'], { encoding: 'utf8' });
    assert.equal(extracted.status, 0, extracted.stderr);
    assert.equal(extracted.stdout, 'محتوای واقعی آزمایشی');
    const before = files.map((file) => readFileSync(join(backups, file)));
    const collision = spawnSync('sh', [script], { env, encoding: 'utf8' });
    assert.notEqual(collision.status, 0);
    assert.match(collision.stderr, /Refusing to overwrite/);
    files.forEach((file, index) => assert.deepEqual(readFileSync(join(backups, file)), before[index]));
  } finally {
    rmSync(scratch, { recursive: true, force: true });
  }
});

const fakeHost = `#!${process.execPath}
const fs = require('node:fs');
const path = require('node:path');
const cp = require('node:child_process');
const tool = path.basename(process.argv[1]);
const args = process.argv.slice(2);
const base = process.env.CHIDEMOON_FIXTURE_ROOT;
const mode = process.env.CHIDEMOON_FIXTURE_FAILURE;
const log = (message) => fs.appendFileSync(path.join(base, 'commands.log'), message + '\\n');
const maintenance = path.join(base, 'site', '.maintenance');
const uploads = path.join(base, 'uploads');
if (tool === 'flock') process.exit(0);
if (tool === 'stat') { process.stdout.write(String(fs.statSync(args.at(-1)).size)); process.exit(0); }
if (tool === 'readlink') { process.stdout.write(fs.realpathSync(args.at(-1))); process.exit(0); }
if (tool === 'mv') { fs.renameSync(args.at(-2), args.at(-1)); process.exit(0); }
if (tool === 'df') {
 const counterFile = path.join(base, 'disk-count');
 const count = fs.existsSync(counterFile) ? Number(fs.readFileSync(counterFile)) + 1 : 1;
 fs.writeFileSync(counterFile, String(count));
 const free = mode === 'disk-after-backup' && count > 1 ? 0 : 1099511627776;
 process.stdout.write('Filesystem 1-blocks Used Available Capacity Mounted on\\nfixture 2199023255552 0 ' + free + ' 0% /\\n');
 process.exit(0);
}
if (tool !== 'docker' || args[0] !== 'compose') throw new Error('Unexpected fixture command: ' + tool);
const configIndex = args.indexOf('-f');
const release = path.basename(path.dirname(args[configIndex + 1]));
const command = args.slice(configIndex + 2);
const text = command.join(' ');
log(release + '|' + text);
if (text === 'config -q') process.exit(0);
if (text.startsWith('up ')) process.exit(0);
if (command[0] === 'run') {
 if (command.includes('--entrypoint')) process.exit(0);
 const wp = command.slice(command.indexOf('wpcli') + 1);
 const action = wp.join(' ');
 if (action.includes('option get stylesheet')) { process.stdout.write('hello-elementor\\n'); process.exit(0); }
 if (action.includes('maintenance-mode activate')) { fs.writeFileSync(maintenance, 'active'); process.exit(0); }
 if (action.includes('maintenance-mode deactivate')) {
  if (fs.readFileSync(maintenance, 'utf8').includes('upgrading = time();')) process.exit(1);
  fs.rmSync(maintenance, {force:true}); process.exit(0);
 }
 if (action.includes('elementor-editability-upgrade.php apply')) {
  fs.writeFileSync(path.join(base, 'database-state'), 'changed');
  fs.writeFileSync(path.join(uploads, 'generated.css'), 'new');
  if (mode === 'migration') process.exit(24);
 }
 if (action.includes('eval-file') || action.includes('plugin is-active') || action === 'core is-installed' || action === 'rewrite flush --hard') process.exit(0);
 throw new Error('Unexpected fixture WP command: ' + action);
}
if (text.includes('database du -sk')) { process.stdout.write('64 /var/lib/mysql\\n'); process.exit(0); }
if (text.includes('wordpress du -sk')) { process.stdout.write('8 /uploads\\n'); process.exit(0); }
if (text.includes('wordpress test -f')) process.exit(fs.existsSync(maintenance) ? 0 : 1);
if (text.includes('exec mariadb-dump')) {
 process.stdout.write(fs.readFileSync(path.join(base, 'fixture.sql')));
 process.exit(mode === 'dump' ? 7 : 0);
}
if (text.includes('exec mariadb --')) {
 fs.writeFileSync(path.join(base, 'restored.sql'), fs.readFileSync(0));
 fs.writeFileSync(path.join(base, 'database-state'), 'original');
 process.exit(0);
}
if (text.includes('wordpress tar -C') && command.includes('-czf')) {
 const result = cp.spawnSync('tar', ['-C', uploads, '-czf', '-', '.']);
 process.stdout.write(result.stdout); process.stderr.write(result.stderr); process.exit(result.status);
}
if (text.includes('wordpress tar -C') && command.includes('-xzf')) {
 const result = cp.spawnSync('tar', ['-C', uploads, '-xzf', '-'], {input: fs.readFileSync(0)});
 process.stdout.write(result.stdout); process.stderr.write(result.stderr); process.exit(result.status);
}
if (text.includes('find /var/www/html/wp-content/uploads')) { for (const file of fs.readdirSync(uploads)) fs.rmSync(path.join(uploads,file),{recursive:true,force:true}); process.exit(0); }
if (text.includes('wordpress rm -f')) { fs.rmSync(maintenance,{force:true}); process.exit(0); }
if (text.includes('file_put_contents')) {
 const code = command.at(-1).replaceAll('/var/www/html/.maintenance', maintenance);
 const result = cp.spawnSync('php', ['-r', code]);
 if (!fs.readFileSync(maintenance, 'utf8').includes('upgrading = time();')) throw new Error('Maintenance must survive requests after ten minutes.');
 process.stderr.write(result.stderr); process.exit(result.status);
}
if (text.includes('file_get_contents')) process.exit(mode === 'late-health' ? 23 : 0);
throw new Error('Unexpected fixture Docker command: ' + text);
`;

for (const mode of ['disk-after-backup', 'dump', 'migration', 'late-health', 'success']) {
  test(`selective deployment ${mode} preserves the correct release, data and maintenance state`, () => {
    const scratch = mkdtempSync(join(tmpdir(), 'chidemoon-deploy-'));
    try {
      const bin = join(scratch, 'bin');
      const ops = join(scratch, 'ops');
      const deploy = join(scratch, 'deploy');
      const previous = join(deploy, 'releases', 'chidemoon-release-original');
      const archiveRoot = join(scratch, 'source', 'chidemoon-release-fixture');
      for (const path of [bin, ops, previous, join(archiveRoot, 'tools'), join(scratch, 'site'), join(scratch, 'uploads')]) mkdirSync(path, { recursive: true });
      writeFileSync(join(previous, 'compose.yml'), 'name: fixture\n');
      writeFileSync(join(archiveRoot, 'compose.yml'), 'name: fixture\n');
      for (const tool of ['elementor-editability-upgrade.php', 'editorial-elementor-upgrade.php', 'verify-elementor.php']) writeFileSync(join(archiveRoot, 'tools', tool), '<?php // isolated fixture\n');
      writeFileSync(join(scratch, 'fixture.sql'), '-- MariaDB dump\nCREATE TABLE `wp_posts` (`ID` bigint);\nINSERT INTO `wp_posts` VALUES (42);\n-- Dump completed on 2026-01-01\n');
      writeFileSync(join(scratch, 'database-state'), 'original');
      writeFileSync(join(scratch, 'uploads', 'editorial.txt'), 'original media');
      writeFileSync(join(deploy, '.env'), 'CHIDEMOON_ENVIRONMENT=local\n');
      symlinkSync(previous, join(deploy, 'current'));
      const bundle = join(scratch, 'source.tar.gz');
      const archive = spawnSync('tar', ['-C', join(scratch, 'source'), '-czf', bundle, 'chidemoon-release-fixture']);
      assert.equal(archive.status, 0, archive.stderr.toString());
      const images = join(scratch, 'images.tar.gz');
      writeFileSync(images, 'isolated image archive fixture');
      for (const name of ['docker', 'flock', 'df', 'stat', 'readlink', 'mv']) writeFileSync(join(bin, name), fakeHost, { mode: 0o755 });
      for (const name of ['verify-release-bundle.sh', 'load-offline-image-archive.sh']) writeFileSync(join(ops, name), '#!/bin/sh\nprintf "sealed-archive-check\\n" >> "$CHIDEMOON_FIXTURE_ROOT/commands.log"\n', { mode: 0o755 });
      const wrapper = join(ops, 'deploy-elementor-upgrade.sh');
      writeFileSync(wrapper, readFileSync(join(root, 'ops/deploy-elementor-upgrade.sh')));
      const result = spawnSync('bash', [wrapper, bundle, images], {
        env: { ...process.env, PATH: `${bin}:${process.env.PATH}`, CHIDEMOON_DEPLOY_ROOT: deploy, CHIDEMOON_MIN_FREE_MIB: '1', CHIDEMOON_FIXTURE_ROOT: scratch, CHIDEMOON_FIXTURE_FAILURE: mode },
        encoding: 'utf8',
      });
      const log = readFileSync(join(scratch, 'commands.log'), 'utf8');
      assert.equal(existsSync(join(scratch, 'site', '.maintenance')), false, `${mode} left maintenance active: ${result.stderr}`);
      if (mode === 'success') {
        assert.equal(result.status, 0, result.stderr);
        assert.equal(realpathSync(join(deploy, 'current')), realpathSync(join(deploy, 'releases', 'chidemoon-release-fixture')));
        assert.equal(readFileSync(join(scratch, 'database-state'), 'utf8'), 'changed');
        assert.match(log, /editorial-elementor-upgrade\.php apply products/);
        assert.ok(log.indexOf('/tools/verify-elementor.php native') < log.indexOf('wordpress rm -f /var/www/html/.maintenance'));
      } else {
        assert.notEqual(result.status, 0, `${mode} should fail`);
        assert.equal(realpathSync(join(deploy, 'current')), realpathSync(previous), result.stderr);
        assert.equal((result.stderr.match(/Upgrade failed\. Restoring/g) ?? []).length, 1, 'Only the controlling shell may perform recovery.');
        assert.equal(readFileSync(join(scratch, 'database-state'), 'utf8'), 'original', result.stderr);
        assert.deepEqual(readdirSync(join(scratch, 'uploads')), ['editorial.txt']);
        assert.equal(readFileSync(join(scratch, 'uploads', 'editorial.txt'), 'utf8'), 'original media');
        if (['migration', 'late-health'].includes(mode)) assert.equal(readFileSync(join(scratch, 'restored.sql'), 'utf8'), readFileSync(join(scratch, 'fixture.sql'), 'utf8'));
        if (mode === 'late-health') assert.ok(log.lastIndexOf('file_put_contents') < log.indexOf('exec mariadb --'), 'Re-enter maintenance before restoring the database.');
        if (['disk-after-backup', 'dump'].includes(mode)) assert.doesNotMatch(log, /elementor-editability-upgrade\.php apply/);
      }
      assert.doesNotMatch(log, /reset-demo|elementor-rebuild\.php|rebuild-editorial\.php/);
    } finally {
      rmSync(scratch, { recursive: true, force: true });
    }
  });
}

// These cases exercise installation/promotion behavior with disposable host
// commands. PHP ABI and binary compatibility remain real Docker acceptance gates.
const fakeInstallerHost = `#!${process.execPath}
const fs = require('node:fs');
const path = require('node:path');
const cp = require('node:child_process');
const tool = path.basename(process.argv[1]);
const args = process.argv.slice(2);
if (tool === 'uname') { process.stdout.write(args[0] === '-s' ? 'Linux\\n' : 'x86_64\\n'); process.exit(0); }
if (tool === 'id') { process.stdout.write('0\\n'); process.exit(0); }
if (tool === 'flock' || tool === 'chown') process.exit(0);
if (tool === 'file') { process.stdout.write(args[0] + ': ELF 64-bit LSB shared object, x86-64\\n'); process.exit(0); }
if (tool === 'readlink') { process.stdout.write(fs.realpathSync(args.at(-1))); process.exit(0); }
if (tool === 'mv') { fs.renameSync(args.at(-2), args.at(-1)); process.exit(0); }
if (tool !== 'docker' || args[0] !== 'run') throw new Error('Unexpected fixture command: ' + tool);
const text = args.join(' ');
if (text.includes('PHP_MAJOR_VERSION')) { process.stdout.write('8.2|Linux|0|0|64|x86_64'); process.exit(0); }
if (text.includes('ldd --version')) process.exit(0);
if (!text.includes('ioncube_loader_iversion() !== $expected') || !text.includes('phpversion("ionCube Loader") !== $argv[1]') || !text.endsWith('-- 15.5.1')) throw new Error('Expected exact loader-version gate.');
if (!text.includes('extension_loaded("mysqli")') || !text.includes('extension_loaded("curl")')) throw new Error('Stock extension gate missing.');
if (process.env.CHIDEMOON_FIXTURE_FAILURE === 'musl' && text.includes('/musl/conf.d')) process.exit(19);
// The legacy string API reports 15.5 for the official 15.5.1 binary. Run the
// exact PHP check with documented precise APIs and that observed legacy value.
const phpFixture = 'namespace ChidemoonOpsFixture; function extension_loaded($name) { return true; } function function_exists($name) { return true; } function ioncube_loader_version() { return "15.5"; } function ioncube_loader_iversion() { return 150501; } function phpversion($name = null) { return "15.5.1"; } ' + args[args.indexOf('-r') + 1];
const checked = cp.spawnSync('php', ['-r', phpFixture, '--', args.at(-1)]);
process.stdout.write(checked.stdout); process.stderr.write(checked.stderr); process.exit(checked.status);
`;

for (const mode of ['musl', 'checksum', 'success']) {
  test(`ionCube ${mode} preserves prior runtime and activates only a complete verified pair`, () => {
    const scratch = realpathSync(mkdtempSync(join(tmpdir(), 'chidemoon-ioncube-')));
    try {
      const bin = join(scratch, 'bin');
      const runtime = join(scratch, 'runtime');
      const oldRuntime = join(runtime, 'ioncube-8.2-prior');
      for (const path of [bin, oldRuntime]) mkdirSync(path, { recursive: true });
      writeFileSync(join(oldRuntime, 'retained.ini'), 'previous verified loader configuration\n');
      writeFileSync(join(runtime, 'ioncube-previous.txt'), 'retained earlier recovery point\n');
      symlinkSync('ioncube-8.2-prior', join(runtime, 'ioncube'));
      const bytes = 'disposable x86_64 shared-object fixture';
      const archives = {};
      const digests = {};
      for (const abi of ['glibc', 'musl']) {
        const source = join(scratch, 'source', abi);
        mkdirSync(join(source, 'ioncube'), { recursive: true });
        const loader = `ioncube_loader_${abi === 'musl' ? 'lin-musl' : 'lin'}_8.2.so`;
        writeFileSync(join(source, 'ioncube', loader), bytes);
        archives[abi] = join(scratch, `official-${abi}-fixture.tar.gz`);
        const packed = spawnSync('tar', ['-C', source, '-czf', archives[abi], 'ioncube']);
        assert.equal(packed.status, 0, packed.stderr.toString());
        digests[abi] = createHash('sha256').update(readFileSync(archives[abi])).digest('hex');
      }
      for (const name of ['uname', 'id', 'flock', 'chown', 'file', 'readlink', 'mv', 'docker']) writeFileSync(join(bin, name), fakeInstallerHost, { mode: 0o755 });
      const env = {
        ...process.env,
        PATH: `${bin}:${process.env.PATH}`,
        CHIDEMOON_RUNTIME_DIR: runtime,
        CHIDEMOON_IONCUBE_GLIBC_ARCHIVE: archives.glibc,
        CHIDEMOON_IONCUBE_MUSL_ARCHIVE: archives.musl,
        CHIDEMOON_IONCUBE_GLIBC_SHA256: mode === 'checksum' ? '0'.repeat(64) : digests.glibc,
        CHIDEMOON_IONCUBE_MUSL_SHA256: digests.musl,
        CHIDEMOON_FIXTURE_FAILURE: mode,
      };
      const result = spawnSync('bash', [join(root, 'ops/install-ioncube.sh')], { env, encoding: 'utf8' });
      assert.equal(readFileSync(join(oldRuntime, 'retained.ini'), 'utf8'), 'previous verified loader configuration\n');
      assert.deepEqual(readdirSync(runtime).filter((name) => name.startsWith('.ioncube-stage.')), [], 'Failed staging must be removed.');
      if (mode !== 'success') {
        assert.notEqual(result.status, 0, result.stderr);
        assert.equal(realpathSync(join(runtime, 'ioncube')), oldRuntime);
        assert.equal(readFileSync(join(runtime, 'ioncube-previous.txt'), 'utf8'), 'retained earlier recovery point\n');
        assert.deepEqual(readdirSync(runtime).filter((name) => name.startsWith('ioncube-history-')), []);
      } else {
        assert.equal(result.status, 0, result.stderr);
        const active = realpathSync(join(runtime, 'ioncube'));
        assert.notEqual(active, oldRuntime);
        assert.equal(readFileSync(join(runtime, 'ioncube-previous.txt'), 'utf8').trim(), oldRuntime);
        for (const abi of ['glibc', 'musl']) {
          const loader = `ioncube_loader_${abi === 'musl' ? 'lin-musl' : 'lin'}_8.2.so`;
          assert.equal(readFileSync(join(active, abi, loader), 'utf8'), bytes);
          assert.equal(readFileSync(join(active, abi, 'conf.d', '00-ioncube.ini'), 'utf8'), `zend_extension=/opt/chidemoon-ioncube/${abi}/${loader}\n`);
          assert.equal(statSync(join(active, abi, loader)).mode & 0o777, 0o644);
          assert.equal(statSync(join(active, abi, 'conf.d')).mode & 0o777, 0o755);
        }
        const checked = spawnSync('sha256sum', ['-c', 'installed-files.sha256'], { cwd: active, encoding: 'utf8' });
        assert.equal(checked.status, 0, checked.stderr);
        const history = readdirSync(runtime).filter((name) => name.startsWith('ioncube-history-'));
        assert.equal(history.length, 1);
        const repeated = spawnSync('bash', [join(root, 'ops/install-ioncube.sh')], { env, encoding: 'utf8' });
        assert.equal(repeated.status, 0, repeated.stderr);
        assert.equal(realpathSync(join(runtime, 'ioncube')), active);
        assert.equal(readFileSync(join(runtime, 'ioncube-previous.txt'), 'utf8').trim(), oldRuntime, 'Idempotent installation retains the genuine previous runtime.');
        assert.deepEqual(readdirSync(runtime).filter((name) => name.startsWith('ioncube-history-')), history);
      }
    } finally {
      rmSync(scratch, { recursive: true, force: true });
    }
  });
}
