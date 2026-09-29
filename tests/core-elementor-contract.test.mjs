import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const read = (path) => readFileSync(join(root, path), 'utf8');
const core = 'plugins/chidemoon-core';

describe('native Elementor ownership', () => {
  it('removes obsolete theme and feed renderers', () => {
    for (const path of ['themes/chidemoon-blocksy-child/functions.php', 'legacy/DO-NOT-LOAD.txt', 'vendor/blocksy.zip', `${core}/includes/class-chidemoon-core-landing-components.php`, 'tools/seed-editorial.php']) {
      assert.equal(existsSync(join(root, path)), false, path);
    }
    const rebuild = read('tools/elementor-rebuild.php');
    assert.doesNotMatch(rebuild, /widget\( '(?:html|shortcode|search-form)'/);
    assert.match(rebuild, /widget\( 'search'/);
  });

  it('keeps affiliate eligibility across all comparison surfaces', () => {
    const compare = read(`${core}/includes/class-chidemoon-core-compare.php`);
    assert.match(compare, /Chidemoon_Core_Affiliate::is_publicly_eligible\( \$product \)/);
    for (const marker of ['data-comparison-status', 'data-comparison-search-input', 'data-compare-product', 'nofollow sponsored noopener']) assert.ok(compare.includes(marker));
  });

  it('shares Shop-the-Look rendering and provides human editing controls', () => {
    const look = read(`${core}/includes/class-chidemoon-core-shop-the-look.php`);
    const widget = read(`${core}/includes/class-chidemoon-core-elementor-shop-look-widget.php`);
    assert.match(look, /function enqueue_assets/);
    assert.match(widget, /Chidemoon_Core_Shop_The_Look::enqueue_assets\(\)/);
    assert.match(widget, /Controls_Manager::SELECT2/);
    const compare = read(`${core}/includes/class-chidemoon-core-elementor-compare-widget.php`);
    for (const control of ['search_label', 'search_placeholder', 'empty_title', 'columns', 'card_padding', 'button_padding']) assert.ok(compare.includes(control));
  });
});
