import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, rmSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';

const projectRoot = process.cwd();
const temporaryRoots: string[] = [];

function createSealedCandidate(): string {
  const root = mkdtempSync(join(tmpdir(), 'kalahamoon-chidemoon-deploy-'));
  temporaryRoots.push(root);
  const files: Record<string, string> = {
    '.env': 'POSTGRES_USER=test\nPOSTGRES_DB=test\n',
    'compose.prod.yml': 'services: {}\n',
    'release.env': 'release_id=test-release\n',
    'release-validation.status': 'release_gate=passed\nmigration_safety=passed\ndatabase_contract_changes=none\n',
    '.next/standalone/server.js': 'server\n',
    '.next/standalone/node_modules/next/package.json': '{}\n',
    '.next/standalone/node_modules/next/dist/server/node-environment-extensions/console-file.js': 'console\n',
    '.next/standalone/node_modules/next/dist/server/dev/browser-logs/file-logger.js': 'logger\n',
    'dist/worker.js': 'worker\n',
    'dist/release-import.js': 'release-import\n',
    'dist/product-identity-migration-preflight.js': 'product-identity-migration-preflight\n',
    'dist/runtime-db-role-provision.js': 'runtime-db-role-provision\n',
    'releases/2026-08-01-kalahamoon-1-0-0.json': '{}\n',
    'Dockerfile.prebuilt': 'FROM scratch\n',
    'chidemoon/packages/chidemoon-theme.zip': 'theme-package\n',
    'chidemoon/packages/kalahamoon.zip': 'plugin-package\n',
    'chidemoon/packages/manifest.json': '{}\n',
    'chidemoon/packages/installed-files.sha256': '',
    'deploy/chidemoon/install.sh': '#!/usr/bin/env bash\n',
  };

  for (const [relativePath, source] of Object.entries(files)) {
    const path = join(root, relativePath);
    mkdirSync(join(path, '..'), { recursive: true });
    writeFileSync(path, source);
  }
  files['chidemoon/packages/manifest.sha256'] = execFileSync(
    'shasum',
    ['-a', '256', 'chidemoon-theme.zip', 'kalahamoon.zip'],
    { cwd: join(root, 'chidemoon/packages'), encoding: 'utf8' },
  );
  writeFileSync(join(root, 'chidemoon/packages/manifest.sha256'), files['chidemoon/packages/manifest.sha256']);
  const manifest = Object.keys(files).sort().map((relativePath) => (
    execFileSync('shasum', ['-a', '256', relativePath], { cwd: root, encoding: 'utf8' })
  )).join('');
  writeFileSync(join(root, 'release-manifest.sha256'), manifest);
  symlinkSync(join(projectRoot, 'scripts'), join(root, 'scripts'), 'dir');
  return root;
}

afterEach(() => {
  for (const root of temporaryRoots.splice(0)) {
    rmSync(root, { recursive: true, force: true });
  }
});

describe('guarded Chidemoon deployment', () => {
  it('rejects the retired runtime content repair mode', () => {
    const candidate = createSealedCandidate();
    const result = spawnSync('bash', [join(candidate, 'scripts/deploy-chidemoon-vps.sh')], {
      cwd: candidate,
      env: {
        ...process.env,
        PROJECT_ROOT: candidate,
        CHIDEMOON_CONTENT_ONLY_REPAIR: 'I_UNDERSTAND_CONTENT_ONLY_REPAIR',
        CHIDEMOON_DEPLOY_DRY_RUN: 'true',
      },
      encoding: 'utf8',
    });

    expect(result.status).not.toBe(0);
    expect(`${result.stdout}${result.stderr}`).toContain('CHIDEMOON_CONTENT_ONLY_REPAIR is retired');
  });

  it('permits only a sealed, database-contract-free theme asset repair', () => {
    const candidate = createSealedCandidate();
    const result = spawnSync('bash', [join(candidate, 'scripts/deploy-chidemoon-vps.sh')], {
      cwd: candidate,
      env: {
        ...process.env,
        PROJECT_ROOT: candidate,
        CHIDEMOON_THEME_ASSET_REPAIR: 'I_UNDERSTAND_WORDPRESS_THEME_ASSET_REPAIR_WITHOUT_DATA_MIGRATION',
        CHIDEMOON_CATALOG_CONNECTOR_CLIENT_ID: 'test-client',
        CHIDEMOON_CATALOG_CONNECTOR_CLIENT_SECRET: 'test-secret',
        CHIDEMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE: 'catalog_origin_test_challenge',
        CHIDEMOON_DEPLOY_DRY_RUN: 'true',
      },
      encoding: 'utf8',
    });

    expect(result.status, result.stderr).toBe(0);
    expect(result.stdout).toContain('theme asset repair');
    expect(result.stdout).toContain('Applying sealed Chidemoon theme asset repair');
    expect(result.stdout).not.toContain('backup');
  });

  it('requires an explicit non-ready bootstrap sentinel before installing an unprovisioned consumer', () => {
    const candidate = createSealedCandidate();
    const result = spawnSync('bash', [join(candidate, 'scripts/deploy-chidemoon-vps.sh')], {
      cwd: candidate,
      env: {
        ...process.env,
        PROJECT_ROOT: candidate,
        CHIDEMOON_CATALOG_CONNECTOR_CLIENT_ID: '',
        CHIDEMOON_CATALOG_CONNECTOR_CLIENT_SECRET: '',
        CHIDEMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE: '',
        CHIDEMOON_DEPLOY_DRY_RUN: 'true',
      },
      encoding: 'utf8',
    });

    expect(result.status).not.toBe(0);
    expect(`${result.stdout}${result.stderr}`).toContain('Catalog connector configuration is incomplete');
    expect(`${result.stdout}${result.stderr}`).not.toContain('remove only the retired Chidemoon MU content transform');
  });

  it('allows a sealed consumer bootstrap only when it is explicitly non-ready and unprovisioned', () => {
    const candidate = createSealedCandidate();
    const result = spawnSync('bash', [join(candidate, 'scripts/deploy-chidemoon-vps.sh')], {
      cwd: candidate,
      env: {
        ...process.env,
        PROJECT_ROOT: candidate,
        CHIDEMOON_CONSUMER_BOOTSTRAP: 'I_UNDERSTAND_CATALOG_CONSUMER_BOOTSTRAP_IS_NOT_LAUNCH_READY',
        CHIDEMOON_REQUIRE_LAUNCH_READY: 'false',
        CHIDEMOON_CATALOG_CONNECTOR_CLIENT_ID: '',
        CHIDEMOON_CATALOG_CONNECTOR_CLIENT_SECRET: '',
        CHIDEMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE: '',
        CHIDEMOON_DEPLOY_DRY_RUN: 'true',
      },
      encoding: 'utf8',
    });

    expect(result.status, result.stderr).toBe(0);
    expect(result.stdout).toContain('Bootstrapping sealed Chidemoon consumer assets before connector provisioning');
    expect(result.stdout).toContain('intentionally non-ready catalog consumer');
    expect(result.stdout).toContain('remains intentionally not launch-ready');
    expect(`${result.stdout}${result.stderr}`).toContain('compose stop chidemoon-cron');
  });

  it('rejects bootstrap when it would be treated as launch-ready or carries connector values', () => {
    const candidate = createSealedCandidate();
    const launchReadyResult = spawnSync('bash', [join(candidate, 'scripts/deploy-chidemoon-vps.sh')], {
      cwd: candidate,
      env: {
        ...process.env,
        PROJECT_ROOT: candidate,
        CHIDEMOON_CONSUMER_BOOTSTRAP: 'I_UNDERSTAND_CATALOG_CONSUMER_BOOTSTRAP_IS_NOT_LAUNCH_READY',
        CHIDEMOON_CATALOG_CONNECTOR_CLIENT_ID: '',
        CHIDEMOON_CATALOG_CONNECTOR_CLIENT_SECRET: '',
        CHIDEMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE: '',
        CHIDEMOON_DEPLOY_DRY_RUN: 'true',
      },
      encoding: 'utf8',
    });
    const provisionedResult = spawnSync('bash', [join(candidate, 'scripts/deploy-chidemoon-vps.sh')], {
      cwd: candidate,
      env: {
        ...process.env,
        PROJECT_ROOT: candidate,
        CHIDEMOON_CONSUMER_BOOTSTRAP: 'I_UNDERSTAND_CATALOG_CONSUMER_BOOTSTRAP_IS_NOT_LAUNCH_READY',
        CHIDEMOON_REQUIRE_LAUNCH_READY: 'false',
        CHIDEMOON_CATALOG_CONNECTOR_CLIENT_ID: 'test-client',
        CHIDEMOON_CATALOG_CONNECTOR_CLIENT_SECRET: '',
        CHIDEMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE: '',
        CHIDEMOON_DEPLOY_DRY_RUN: 'true',
      },
      encoding: 'utf8',
    });

    expect(launchReadyResult.status).not.toBe(0);
    expect(`${launchReadyResult.stdout}${launchReadyResult.stderr}`).toContain('requires CHIDEMOON_REQUIRE_LAUNCH_READY=false');
    expect(provisionedResult.status).not.toBe(0);
    expect(`${provisionedResult.stdout}${provisionedResult.stderr}`).toContain('only for an unprovisioned consumer');
  });

  it('uses a sealed prebuilt package and validates deployment readiness without backup gates', () => {
    const candidate = createSealedCandidate();
    const result = spawnSync('bash', [join(candidate, 'scripts/deploy-chidemoon-vps.sh')], {
      cwd: candidate,
      env: {
        ...process.env,
        PROJECT_ROOT: candidate,
        CHIDEMOON_CATALOG_CONNECTOR_CLIENT_ID: 'test-client',
        CHIDEMOON_CATALOG_CONNECTOR_CLIENT_SECRET: 'test-secret',
        CHIDEMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE: 'catalog_origin_test_challenge',
        CHIDEMOON_DEPLOY_DRY_RUN: 'true',
      },
      encoding: 'utf8',
    });

    expect(result.status, result.stderr).toBe(0);
    expect(result.stdout).toContain('Validating sealed Chidemoon release packages');
    expect(result.stdout).toContain('remove only the retired Chidemoon MU content transform');
    expect(`${result.stdout}${result.stderr}`).not.toContain('wp chidemoon rebuild-site');
    expect(`${result.stdout}${result.stderr}`).not.toContain('CHIDEMOON_CONTENT_ONLY_REPAIR_WITH_BACKUP');
    expect(`${result.stdout}${result.stderr}`).not.toContain('build-packages.sh');
  });

  it('rejects a candidate missing its release manifest', () => {
    const candidate = createSealedCandidate();
    rmSync(join(candidate, 'release-manifest.sha256'));

    const result = spawnSync('bash', [join(candidate, 'scripts/deploy-chidemoon-vps.sh')], {
      cwd: candidate,
      env: {
        ...process.env,
        PROJECT_ROOT: candidate,
        CHIDEMOON_DEPLOY_DRY_RUN: 'true',
      },
      encoding: 'utf8',
    });

    expect(result.status).not.toBe(0);
  });
});
