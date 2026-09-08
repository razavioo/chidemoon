import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const read = (path) => readFileSync(join(root, path), 'utf8');

describe('product facts editor', () => {
  const source = () => read('plugins/chidemoon-core/assets/js/product-admin.js');

  it('recognizes supported object and label-value array facts', () => {
    const editor = source();

    assert.match(editor, /Array\.isArray\(data\)/);
    assert.match(editor, /'label' in item/);
    assert.match(editor, /'value' in item/);
    assert.match(editor, /typeof value === 'string' \|\| typeof value === 'number'/);
    assert.match(editor, /state: 'supported'/);
  });

  it('preserves malformed or unsupported facts for manual repair', () => {
    const editor = source();

    assert.match(editor, /state: 'invalid'/);
    assert.match(editor, /function showInvalidFacts/);
    assert.match(editor, /textarea\.style\.display = 'block'/);
    assert.match(editor, /addBtn\.disabled = true/);
    assert.match(editor, /بدون تغییر نگه داشته شد/);
    assert.doesNotMatch(editor, /var rows = parseFacts\(\);[\s\S]*?syncToTextarea\(rows\);/);
  });
});
