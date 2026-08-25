import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

describe('Chidemoon runtime configuration', () => {
  const runtime = readFileSync(
    join(__dirname, '../../deploy/chidemoon/mu-plugins/kalahamoon-runtime.php'),
    'utf8',
  );
  const deployScript = readFileSync(
    join(__dirname, '../../scripts/deploy-chidemoon-vps.sh'),
    'utf8',
  );
  const launchReadiness = readFileSync(
    join(__dirname, '../../deploy/chidemoon/mu-plugins/chidemoon-launch-readiness.php'),
    'utf8',
  );

  it('does not inject a site-specific Kalahamoon credential', () => {
    expect(runtime).not.toContain('CHIDEMOON_KALAHAMOON_API_TOKEN');
  });

  it('explicitly enables this deployment as a read-only catalog consumer', () => {
		expect(runtime).toContain("define( 'KALAHAMOON_CATALOG_CONSUMER_MODE', true )");
		expect(runtime).toContain("'kalahamoon_catalog_refresh_interval_minutes'");
		expect(runtime).toContain("add_filter( 'pre_option_kalahamoon_catalog_authority'");
  });

  it('wires the generic public-origin proof challenge only into the consumer runtime', () => {
    const compose = readFileSync(join(__dirname, '../../compose.prod.yml'), 'utf8');
    const envExample = readFileSync(join(__dirname, '../../.env.production.example'), 'utf8');

    expect(runtime).toContain("getenv( 'KALAHAMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE' )");
    expect(runtime).toContain("define( 'KALAHAMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE'");
    expect(compose.match(/KALAHAMOON_CATALOG_CONNECTOR_CLIENT_ID: \$\{CHIDEMOON_CATALOG_CONNECTOR_CLIENT_ID:-}/g)).toHaveLength(3);
    expect(compose.match(/KALAHAMOON_CATALOG_CONNECTOR_CLIENT_SECRET: \$\{CHIDEMOON_CATALOG_CONNECTOR_CLIENT_SECRET:-}/g)).toHaveLength(3);
    expect(compose.match(/KALAHAMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE: \$\{CHIDEMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE:-}/g)).toHaveLength(3);
    expect(compose).not.toContain('CHIDEMOON_CATALOG_CONNECTOR_CLIENT_ID:?');
    expect(compose).not.toContain('CHIDEMOON_CATALOG_CONNECTOR_CLIENT_SECRET:?');
    expect(compose).not.toContain('CHIDEMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE:?');
    expect(envExample).toMatch(/^CHIDEMOON_CATALOG_CONNECTOR_CLIENT_ID=$/m);
    expect(envExample).toMatch(/^CHIDEMOON_CATALOG_CONNECTOR_CLIENT_SECRET=$/m);
    expect(envExample).toMatch(/^CHIDEMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE=$/m);
    expect(launchReadiness).toContain('Kalahamoon_Catalog_Consumer::has_origin_proof_configuration()');
  });

  it('permits only an explicit, non-ready bootstrap before connector provisioning', () => {
    expect(deployScript).toContain("CONSUMER_BOOTSTRAP_SENTINEL='I_UNDERSTAND_CATALOG_CONSUMER_BOOTSTRAP_IS_NOT_LAUNCH_READY'");
    expect(deployScript).toContain('require_consumer_bootstrap_configuration');
    expect(deployScript).toContain('require_catalog_connector_configuration');
    expect(deployScript).toContain('refresh_chidemoon_runtime false');
    expect(deployScript).toContain('report_bootstrap_non_readiness');
    expect(deployScript).toContain('Consumer bootstrap completed and remains intentionally not launch-ready');
    expect(deployScript).toContain('launch readiness was not granted');
  });

  it('normalizes deployable ZIP archives across machines and source timestamps', () => {
    const packager = readFileSync(join(__dirname, 'build-packages.sh'), 'utf8');

    expect(packager).toContain('mktemp -d');
    expect(packager).toContain('touch -t 198001010000');
    expect(packager).toContain('LC_ALL=C sort');
    expect(packager).toContain('zip -Xq');
    expect(packager).not.toContain('zip -qr');
		expect(packager).not.toContain('build_package "$CHIDEMOON_DIR/plugin/chidemoon-helper"');
  });

  it('verifies every installed theme and plugin file after extraction', () => {
    const packager = readFileSync(join(__dirname, 'build-packages.sh'), 'utf8');

    expect(packager).toContain('write_installed_file_manifest');
    expect(packager).toContain('unzip -Z1');
    expect(packager).toContain('wp-content/themes/chidemoon-theme');
    expect(packager).toContain('wp-content/plugins/kalahamoon');
  });

  it('verifies the public TLS route even when the VPS has no outbound DNS', () => {
    expect(deployScript).toContain('CHIDEMOON_HEALTH_URL#*://');
    expect(deployScript).toContain('--resolve');
    expect(deployScript).toContain('127.0.0.1');
  });

  it('requires sealed packages and service checks before changing WordPress assets', () => {
    expect(deployScript).toContain('verify-release-bundle.sh');
    expect(deployScript).toContain('remove_retired_chidemoon_mu_plugins');
    expect(deployScript).toContain('chidemoon-content-migration.php');
    expect(deployScript).toContain('CHIDEMOON_THEME_ASSET_REPAIR');
    expect(deployScript).toContain('refresh_chidemoon_runtime');
    expect(deployScript).toContain('--force-recreate chidemoon-wordpress chidemoon-cron');
    expect(deployScript).not.toContain('wp chidemoon rebuild-site');
    expect(deployScript).not.toContain('MIGRATION_PLUGIN');
    expect(deployScript).not.toContain('CHIDEMOON_CONTENT_ONLY_REPAIR_WITH_BACKUP');
    expect(deployScript).not.toContain('./chidemoon/deploy/build-packages.sh');
  });

  it('keeps deployment free of site-specific Kalahamoon credentials', () => {
    expect(deployScript).not.toContain('CHIDEMOON_KALAHAMOON_API_TOKEN');
    expect(deployScript).not.toContain('CHIDEMOON_EDITORIAL_');
  });

  it('uses a bounded server-side catalog sync instead of visitor-driven WP-Cron', () => {
    const compose = readFileSync(join(__dirname, '../../compose.prod.yml'), 'utf8');
    const sync = readFileSync(join(__dirname, '../../deploy/chidemoon/catalog-sync.php'), 'utf8');

    expect(compose).toContain('CHIDEMOON_CATALOG_SYNC_INTERVAL_SECONDS');
    expect(compose).toContain('wp eval-file /catalog-sync.php --allow-root');
    expect(compose).toContain('chidemoon-catalog-last-success');
    expect(compose).toContain('chidemoon.com:host-gateway');
    expect(compose).not.toContain('wp cron event run --due-now');
    expect(sync).toContain('Kalahamoon_Catalog_Consumer::is_enabled()');
    expect(sync).toContain('Kalahamoon_API_Products() )->sync_all()');
    expect(sync).toContain("$result['deliveryAcknowledged']");
  });

  it('does not treat a generic cache or stale receipt as a ready catalog consumer', () => {
    expect(launchReadiness).toContain('Kalahamoon_Catalog_Consumer::is_enabled()');
    expect(launchReadiness).toContain('Kalahamoon_Auth::has_catalog_connector_configuration()');
    expect(launchReadiness).toContain('Kalahamoon_Auth::is_connected()');
    expect(launchReadiness).toContain('Kalahamoon_Catalog_Consumer::has_valid_active_snapshot()');
    expect(launchReadiness).toContain('Kalahamoon_Catalog_Consumer::has_confirmed_active_delivery()');
  });

  it('keeps the public shell theme-owned and removes retired runtime transforms', () => {
    const themeRoot = join(__dirname, '../theme/chidemoon-theme');
    const functions = readFileSync(join(themeRoot, 'functions.php'), 'utf8');
    const header = readFileSync(join(themeRoot, 'blocks/site-header/render.php'), 'utf8');
    const footer = readFileSync(join(themeRoot, 'blocks/site-footer/render.php'), 'utf8');
    const script = readFileSync(join(themeRoot, 'assets/js/theme-main.js'), 'utf8');
    const frontPage = readFileSync(join(themeRoot, 'templates/front-page.html'), 'utf8');
    const style = readFileSync(join(themeRoot, 'style.css'), 'utf8');

    expect(functions).toContain("add_editor_style( 'assets/css/editor.css' )");
    expect(functions).toContain("add_filter( 'kalahamoon_enqueue_public_styles'");
    expect(functions).toContain("add_filter( 'kalahamoon_catalog_public_render_urls'");
		expect(functions).toContain('function chidemoon_render_catalog_revision_marker');
		expect(functions).toContain('kalahamoon-catalog-revision');
    expect(functions).toContain('function chidemoon_public_copy');
    expect(functions).not.toContain("add_filter( 'the_content'");
    expect(functions).not.toContain("'article-toc'");
    expect(functions).not.toContain("'kalahamoon-yekanbakh'");
    expect(header).toContain('chidemoon_public_brand_name');
    expect(footer).toContain('chidemoon_public_brand_name');
    expect(header).not.toContain('esc_attr_e(');
    expect(footer).not.toContain('esc_attr_e(');
    expect(header).not.toContain("'Chidemoon'");
    expect(footer).not.toContain("'Chidemoon'");
    expect(header).not.toContain('chidemoon-header-note');
    expect(header).not.toContain('chidemoon-header-subtitle');
    expect(footer).not.toContain('chidemoon-ai-panel');
    expect(footer).not.toContain('chidemoon-footer-cols');
    expect(header).not.toContain('A home that feels like you');
    expect(header).not.toContain('independent discovery');
    expect(footer).not.toContain('Editorial and affiliate policy');
    expect(footer).not.toContain('About and support');
    expect(script).not.toContain('window._paq');
    expect(script).not.toContain('chidemoon-ai-panel');
    expect(frontPage).not.toContain('wp:post-content');
    expect(style).not.toContain('@import');
    expect(style).not.toContain('theme-overrides.css');
    expect(style).not.toContain('refinement.css');
    expect(style).not.toContain('discovery-layout.css');
    expect(style).not.toContain('.chidemoon-home-hero');
    expect(style).not.toContain('.chidemoon-mega-menu');
    expect(style).not.toContain('.chidemoon-ai-');
    expect(style).toContain('Version: 1.6.0');
    expect(existsSync(join(themeRoot, 'assets/css/theme-overrides.css'))).toBe(false);
    expect(existsSync(join(themeRoot, 'assets/css/refinement.css'))).toBe(false);
    expect(existsSync(join(themeRoot, 'assets/css/discovery-layout.css'))).toBe(false);
    expect(existsSync(join(__dirname, '../../deploy/chidemoon/mu-plugins/chidemoon-content-migration.php'))).toBe(false);
    expect(existsSync(join(__dirname, '../../deploy/chidemoon/mu-plugins/kalahamoon-matomo.php'))).toBe(false);
  });
});
