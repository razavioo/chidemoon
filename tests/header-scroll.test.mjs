import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

test('sticky header follows the visible admin bar as the mobile bar scrolls away', () => {
  const events = new Map();
  const offsets = new Map();
  let bottom = 46;
  const document = {
    readyState: 'complete',
    body: { classList: { contains: () => false } },
    documentElement: { style: { setProperty: (key, value) => offsets.set(key, value) } },
    getElementById: () => ({ getBoundingClientRect: () => ({ bottom }) }),
    querySelector: () => null,
  };
  vm.runInNewContext(readFileSync(new URL('../plugins/chidemoon-core/assets/js/public-design.js', import.meta.url), 'utf8'), {
    document,
    window: { addEventListener: (event, callback) => events.set(event, callback) },
    MutationObserver: class { observe() {} },
  });
  assert.equal(offsets.get('--ch-admin-bar-offset'), '46px');
  bottom = -80;
  events.get('scroll')();
  assert.equal(offsets.get('--ch-admin-bar-offset'), '0px', 'Content must not show above the header after the mobile admin bar leaves the screen.');
  bottom = 32;
  events.get('resize')();
  assert.equal(offsets.get('--ch-admin-bar-offset'), '32px', 'The fixed desktop admin bar must remain clear of the header.');
});
