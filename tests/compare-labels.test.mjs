import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const source = readFileSync(join(dirname(fileURLToPath(import.meta.url)), '../plugins/chidemoon-core/assets/js/compare.js'), 'utf8');

test('Elementor comparison copy survives selection changes across card and detail instances', () => {
  const cardLabel = { textContent: '' };
  const detailLabel = { textContent: '' };
  const detailHint = { textContent: '', hidden: false };
  const legacyLabel = { textContent: '' };
  function control(className, dataset, nodes) {
    return {
      dataset: { compareProduct: '42', compareName: 'محصول', compareImage: '/product.jpg', ...dataset },
      attributes: {},
      selected: false,
      classList: {
        contains(name) { return name === className; },
        toggle(name, selected) { if (name === 'is-selected') this.selected = selected; },
      },
      setAttribute(name, value) { this.attributes[name] = value; },
      querySelector(selector) { return nodes[selector] ?? null; },
    };
  }
  const card = control('chidemoon-compare-control', { compareLabel: 'بسنج', compareSelectedLabel: 'حذف انتخاب' }, { span: cardLabel });
  const detail = control('chidemoon-compare-single', {
    compareLabel: 'در کنار محصول دیگر', compareSelectedLabel: 'از فهرست بردار',
    compareHint: '', compareSelectedHint: 'برای حذف کلیک کن',
  }, {
    '.chidemoon-compare-single__label': detailLabel,
    '.chidemoon-compare-single__hint': detailHint,
  });
  const legacy = control('chidemoon-compare-control', { compareProduct: '43' }, { span: legacyLabel });
  let stored = '[]';
  let click;
  const strip = { querySelector() { return null; } };
  const document = {
    readyState: 'complete',
    querySelector(selector) { return selector === '[data-comparison-status]' ? strip : null; },
    querySelectorAll(selector) { return selector === '[data-compare-product]' ? [card, detail, legacy] : []; },
    addEventListener(name, handler) { if (name === 'click') click = handler; },
    documentElement: { classList: { remove() {} }, style: { removeProperty() {} } },
  };
  runInNewContext(source, {
    window: {
      ChidemoonCompare: {
        key: 'compare', maximum: 4, compareUrl: '/product-comparison/',
        labels: { added: 'مقایسهٔ پیش‌فرض', removed: 'انتخاب پیش‌فرض', singleAdd: 'افزودن', singleIn: 'انتخاب', singleHint: 'راهنما', singleRemoveHint: 'حذف' },
      },
      location: { search: '' },
    },
    document,
    localStorage: { getItem() { return stored; }, setItem(_key, value) { stored = value; } },
    URLSearchParams,
  });
  assert.equal(cardLabel.textContent, 'بسنج');
  assert.equal(detailLabel.textContent, 'در کنار محصول دیگر');
  assert.equal(detailHint.hidden, true, 'an explicitly empty editor hint must stay hidden');
  assert.equal(legacyLabel.textContent, 'مقایسهٔ پیش‌فرض', 'non-Elementor controls retain global copy');

  const toggleCard = () => click({
    target: { closest(selector) { return selector === '.chidemoon-compare-control, .chidemoon-compare-single, .chidemoon-comparison-search__result' ? card : null; } },
    preventDefault() {},
  });
  toggleCard();
  assert.deepEqual(JSON.parse(stored).map(({ id }) => id), [42]);
  assert.equal(cardLabel.textContent, 'حذف انتخاب');
  assert.equal(detailLabel.textContent, 'از فهرست بردار');
  assert.equal(detailHint.textContent, 'برای حذف کلیک کن');
  assert.equal(detailHint.hidden, false);
  assert.equal(card.attributes['aria-pressed'], 'true');
  assert.equal(detail.attributes['aria-pressed'], 'true');

  toggleCard();
  assert.deepEqual(JSON.parse(stored), []);
  assert.equal(cardLabel.textContent, 'بسنج');
  assert.equal(detailLabel.textContent, 'در کنار محصول دیگر');
  assert.equal(detailHint.textContent, '');
  assert.equal(detailHint.hidden, true);
  assert.equal(card.attributes['aria-pressed'], 'false');
});
