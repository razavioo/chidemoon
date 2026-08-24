import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const read = (path) => readFileSync(join(root, path), 'utf8');

describe('standalone Chidemoon runtime', () => {
  it('has no legacy catalog runtime dependency', () => {
    const compose = read('compose.yml').toLowerCase();
    const core = read('plugins/chidemoon-core/chidemoon-core.php').toLowerCase();

    for (const forbidden of ['kalahamoon', 'oauth', 'app:3000']) {
      assert.equal(compose.includes(forbidden), false, `compose contains ${forbidden}`);
      assert.equal(core.includes(forbidden), false, `core contains ${forbidden}`);
    }
  });

  it('mounts only Chidemoon-owned plugins and keeps traffic from running cron', () => {
    const compose = read('compose.yml');

    assert.match(compose, /chidemoon-core:ro/);
    assert.match(compose, /chidemoon-ai:ro/);
    assert.match(compose, /DISABLE_WP_CRON/);
    assert.match(compose, /chidemoon_database/);
    assert.match(compose, /chidemoon_uploads/);
    assert.match(compose, /run-backup\.sh/);
    assert.match(read('ops/scheduler-host.sh'), /docker compose/);
  });

  it('enforces affiliate-only products and local, safe public foundations', () => {
    const affiliate = read('plugins/chidemoon-core/includes/class-chidemoon-core-affiliate.php');
    const forms = read('plugins/chidemoon-core/includes/class-chidemoon-core-forms.php');

    assert.match(affiliate, /'external' =>/);
    assert.match(affiliate, /set_manage_stock\( false \)/);
    assert.match(affiliate, /nofollow sponsored noopener/);
    assert.match(affiliate, /woocommerce_product_add_to_cart_url/);
    assert.match(affiliate, /chidemoon_clicks/);
    assert.match(forms, /chidemoon-core\/v1/);
    assert.match(forms, /chidemoon_leads/);
    assert.match(forms, /chidemoon_price_alerts/);
    assert.match(forms, /rate_limited/);
  });

  it('ships a checksum-verified, idempotent file importer', () => {
    const importer = read('plugins/chidemoon-core/includes/class-chidemoon-core-importer.php');

    assert.match(importer, /schemaVersion/);
    assert.match(importer, /organization/);
    assert.match(importer, /items/);
    assert.match(importer, /hash_equals/);
    assert.match(importer, /META_SOURCE_KEY/);
    assert.match(read('plugins/chidemoon-core/includes/class-chidemoon-core-affiliate.php'), /_chidemoon_source_key/);
    assert.match(importer, /--dry-run/);
    assert.match(importer, /--organization-slug/);
    assert.match(importer, /selected Chidemoon source organization/);
    assert.match(importer, /wp_safe_remote_get/);
    assert.match(importer, /redirection'\s*=>\s*0/);
    assert.match(importer, /FILTER_FLAG_NO_PRIV_RANGE/);
  });

  it('registers the independent editorial and affiliate pattern layer', () => {
    const blocks = read('plugins/chidemoon-core/includes/class-chidemoon-core-blocks.php');

    assert.match(blocks, /register_block_pattern_category/);
    for (const pattern of [
      'affiliate-disclosure', 'affiliate-cta', 'product-grid', 'product-card',
      'faq', 'pros-cons', 'rating', 'comparison', 'shop-the-look',
      'testimonials', 'editorial-layout',
    ]) {
      assert.match(blocks, new RegExp(pattern));
    }
  });

  it('keeps the expected standalone entry points present', () => {
    for (const path of [
      'standalone-init.ps1',
      'ops/scheduler-host.sh',
      'ops/backup-host.sh',
      'plugins/chidemoon-core/chidemoon-core.php',
      'themes/chidemoon-blocksy-child/style.css',
    ]) {
      assert.equal(existsSync(join(root, path)), true, `${path} is missing`);
    }
  });

  it('uses a sealed, non-destructive release lane', () => {
    const builder = read('ops/create-release-bundle.sh');
    const verifier = read('ops/verify-release-bundle.sh');
    const deployer = read('ops/deploy-release-bundle.sh');

    assert.match(builder, /archive --format=tar/);
    assert.match(builder, /status --porcelain/);
    assert.match(builder, /release-files\.sha256/);
    assert.match(builder, /blocksy/);
    assert.match(builder, /woocommerce/);
    assert.match(verifier, /sha256sum -c/);
    assert.match(verifier, /--no-same-owner/);
    assert.match(verifier, /forbidden path/);
    assert.match(deployer, /run --rm --no-deps backup/);
    assert.match(deployer, /mv -Tf/);
    assert.doesNotMatch(deployer, /docker compose .*down -v/);
    assert.doesNotMatch(deployer, /docker system prune/);
  });
});
