import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const read = (path) => readFileSync(join(root, path), 'utf8');

describe('Elementor Free landing components', () => {
  const core = 'plugins/chidemoon-core';

  it('registers portable landing components without theme helpers', () => {
    const bootstrap = read(`${core}/chidemoon-core.php`);
    const plugin = read(`${core}/includes/class-chidemoon-core-plugin.php`);
    const landing = read(`${core}/includes/class-chidemoon-core-landing-components.php`);

    assert.match(bootstrap, /class-chidemoon-core-landing-components\.php/);
    assert.match(plugin, /Chidemoon_Core_Landing_Components::register\(\)/);
    for (const shortcode of [
      'chidemoon_guides_feed',
      'chidemoon_comparisons_feed',
      'chidemoon_shop_the_look_feed',
    ]) {
      assert.match(landing, new RegExp(shortcode));
    }
    assert.doesNotMatch(landing, /chidemoon_blocksy_/);
    assert.match(landing, /'shop-the-look'/);
    assert.match(landing, /Chidemoon_Core_Shop_The_Look::TAXONOMY/);
    assert.match(landing, /sanitize_title/);
    assert.match(landing, /paginate_links/);
  });

  it('uses the affiliate eligibility gate for every comparison surface', () => {
    const compare = read(`${core}/includes/class-chidemoon-core-compare.php`);

    assert.match(compare, /function selected_products/);
    assert.match(compare, /Chidemoon_Core_Affiliate::is_publicly_eligible\( \$product \)/);
    assert.match(compare, /return self::selected_products\( \$requested \)/);
    assert.match(compare, /\? self::selected_products\( \$requested \)/);
    assert.match(compare, /render_status_shortcode/);
    assert.match(compare, /render_picker_shortcode/);
    assert.match(compare, /render_comparison_table/);
    assert.match(compare, /data-comparison-status/);
    assert.match(compare, /data-comparison-search-input/);
    assert.match(compare, /data-compare-product/);
    assert.match(compare, /id="chidemoon-comparison-table"/);
    assert.match(compare, /nofollow sponsored noopener/);
  });

  it('ships consistent Shop-the-Look assets for shortcode and Elementor rendering', () => {
    const look = read(`${core}/includes/class-chidemoon-core-shop-the-look.php`);
    const widget = read(`${core}/includes/class-chidemoon-core-elementor-shop-look-widget.php`);

    assert.match(look, /function register_assets/);
    assert.match(look, /function enqueue_assets/);
    assert.match(look, /self::enqueue_assets\(\);/);
    assert.match(look, /chidemoon-shop-the-look-view/);
    assert.match(widget, /Chidemoon_Core_Shop_The_Look::enqueue_assets\(\)/);
    assert.equal(existsSync(join(root, `${core}/assets/css/landing-components.css`)), true);
  });
});
