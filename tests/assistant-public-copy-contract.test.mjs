import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { describe, it } from 'node:test';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const read = (path) => readFileSync(join(root, path), 'utf8');

describe('published-content search UI', () => {
  it('keeps public assistant copy Persian and avoids a default duplicate heading', () => {
    const widget = read('plugins/chidemoon-ai/includes/class-chidemoon-ai-assistant-widget.php');
    const assistant = read('plugins/chidemoon-ai/includes/class-chidemoon-ai-assistant.php');

    assert.match(widget, /'title' => ''/);
    assert.match(widget, /trim\( \(string\) \$attributes\['title'\] \)/);
    assert.match(widget, /موضوع یا پرسش خود را بنویسید/);
    assert.match(widget, /این بخش فقط مطالب منتشرشده/);
    assert.match(assistant, /برای این پرسش، مطلب منتشرشده‌ای پیدا نشد/);
    assert.match(assistant, /تعداد جست‌وجوها در این چند دقیقه زیاد بوده است/);
    assert.doesNotMatch(widget, /Search published sources|Keyword search|Your question/);
  });

  it('announces progress and preserves REST errors for visitors', () => {
    const client = read('plugins/chidemoon-ai/assets/js/assistant.js');

    assert.match(client, /aria-busy/);
    assert.match(client, /button\.disabled = pending/);
    assert.match(client, /در حال جست‌وجو در مطالب منتشرشدهٔ چیدمون/);
    assert.match(client, /text\(error\.message\)/);
    assert.match(client, /finally\(function \(\) \{\s*setPending\(false\)/);
  });
});
