import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { runInNewContext } from 'node:vm';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const source = readFileSync(join(dirname(fileURLToPath(import.meta.url)), '../plugins/chidemoon-core/assets/js/compare.js'), 'utf8');

describe('shared comparison links', () => {
  it('shows only the URL products in table order after validating them', async () => {
    let stored = JSON.stringify([
      { id: 3, name: 'Old selection', image: '/old.jpg' },
      { id: 2, name: 'Second', image: '/second.jpg' },
    ]);
    const count = { textContent: '' };
    const chips = { innerHTML: '' };
    const cta = { hidden: true, href: '' };
    const strip = {
      hidden: false,
      querySelector(selector) {
        return {
          '[data-comparison-status-count]': count,
          '[data-comparison-status-chips]': chips,
          '.chidemoon-comparison-status__cta': cta,
        }[selector] ?? null;
      },
    };
    const document = {
      readyState: 'complete',
      querySelector(selector) { return selector === '[data-comparison-status]' ? strip : null; },
      querySelectorAll() { return []; },
      addEventListener() {},
      createElement() {
        return {
          set textContent(value) { this.value = String(value); },
          get innerHTML() { return this.value ?? ''; },
        };
      },
      documentElement: { classList: { remove() {} }, style: { removeProperty() {} } },
    };
    const config = {
      key: 'compare', maximum: 4, restUrl: '/compare-products', compareUrl: '/comparisons/',
      labels: { count: 'محصول برای مقایسه', needMore: 'یک محصول دیگر', staleSelection: 'انتخاب نامعتبر' },
    };
    const requests = [];
    runInNewContext(source, {
      window: { ChidemoonCompare: config, location: { search: '?products=1,2' } },
      document,
      localStorage: { getItem() { return stored; }, setItem(_key, value) { stored = value; } },
      fetch(url) {
        requests.push(url);
        return Promise.resolve({ ok: true, json: () => Promise.resolve([
          { id: 2, title: 'Second', image: '/second.jpg' },
          { id: 1, title: 'First', image: '/first.jpg' },
        ]) });
      },
      URLSearchParams,
    });

    assert.equal(strip.hidden, true, 'the old selection must stay hidden while validating the URL');
    await new Promise((resolve) => setImmediate(resolve));
    assert.deepEqual(JSON.parse(stored).map((item) => item.id), [1, 2]);
    assert.deepEqual(requests, ['/compare-products?ids=1%2C2']);
    assert.equal(strip.hidden, false);
    assert.match(count.textContent, /۲ محصول/);
    assert.ok(chips.innerHTML.indexOf('First') < chips.innerHTML.indexOf('Second'));
    assert.equal(cta.href, '/comparisons/?products=1%2C2#chidemoon-comparison-table');
  });

  it('uses the server-rendered table when validation is temporarily unavailable', async () => {
    let stored = JSON.stringify([{ id: 3, name: 'Old selection', image: '/old.jpg' }]);
    let click;
    let destination;
    const tableButtons = [
      { dataset: { compareProduct: '1', compareName: 'First' } },
      { dataset: { compareProduct: '2', compareName: 'Second' } },
    ];
    const table = { querySelectorAll() { return tableButtons; } };
    const strip = {
      hidden: true,
      querySelector(selector) {
        return {
          '[data-comparison-status-count]': { textContent: '' },
          '[data-comparison-status-chips]': { innerHTML: '' },
          '.chidemoon-comparison-status__cta': { hidden: true, href: '' },
        }[selector] ?? null;
      },
    };
    const document = {
      readyState: 'complete',
      querySelector(selector) {
        return { '[data-comparison-status]': strip, '.chidemoon-comparison-table': table }[selector] ?? null;
      },
      querySelectorAll() { return []; },
      addEventListener(name, handler) { if (name === 'click') click = handler; },
      getElementById() { return null; },
      createElement() { return { set textContent(value) { this.value = String(value); }, get innerHTML() { return this.value ?? ''; }, setAttribute() {} }; },
      body: { appendChild() {} },
      documentElement: { classList: { remove() {} }, style: { removeProperty() {} } },
    };
    const config = {
      key: 'compare', maximum: 4, restUrl: '/compare-products', compareUrl: '/comparisons/',
      labels: { count: 'محصول برای مقایسه', searchError: 'خطای شبکه', needMore: 'یک محصول دیگر' },
    };
    runInNewContext(source, {
      window: { ChidemoonCompare: config, location: { search: '?products=1,2', assign(url) { destination = url; } } },
      document,
      localStorage: { getItem() { return stored; }, setItem(_key, value) { stored = value; } },
      fetch() { return Promise.reject(new Error('offline')); },
      URLSearchParams,
    });

    await new Promise((resolve) => setImmediate(resolve));
    assert.deepEqual(JSON.parse(stored).map((item) => item.id), [1, 2]);
    assert.equal(strip.hidden, false);
    click({ target: { closest(selector) { return selector === '.chidemoon-comparison-table__remove' ? tableButtons[0] : null; } }, preventDefault() {} });
    assert.deepEqual(JSON.parse(stored).map((item) => item.id), [2]);
    assert.equal(destination, '/comparisons/?products=2#chidemoon-comparison-table');
  });

  it('keeps a prior selection if a broken link has no rendered table and validation fails', async () => {
    const original = [{ id: 3, name: 'Saved', image: '/saved.jpg' }];
    let stored = JSON.stringify(original);
    const strip = { hidden: true };
    const document = {
      readyState: 'complete',
      querySelector(selector) { return selector === '[data-comparison-status]' ? strip : null; },
      querySelectorAll() { return []; },
      addEventListener() {},
      getElementById() { return null; },
      createElement() { return { set textContent(value) { this.value = String(value); }, get innerHTML() { return this.value ?? ''; }, setAttribute() {} }; },
      body: { appendChild() {} },
      documentElement: { classList: { remove() {} }, style: { removeProperty() {} } },
    };
    runInNewContext(source, {
      window: {
        ChidemoonCompare: { key: 'compare', maximum: 4, restUrl: '/compare-products', labels: { searchError: 'خطای شبکه' } },
        location: { search: '?products=999' },
      },
      document,
      localStorage: { getItem() { return stored; }, setItem(_key, value) { stored = value; } },
      fetch() { return Promise.reject(new Error('offline')); },
      URLSearchParams,
    });

    await new Promise((resolve) => setImmediate(resolve));
    assert.deepEqual(JSON.parse(stored), original);
    assert.equal(strip.hidden, true);
  });
});
