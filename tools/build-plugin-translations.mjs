import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

for (const domain of ['chidemoon-core', 'chidemoon-ai', 'chidemoon-catalog-sync']) {
 const directory = resolve(import.meta.dirname, '../plugins', domain, 'languages');
 const translations = JSON.parse(readFileSync(resolve(directory, 'fa_IR.json'), 'utf8'));
 const entries = Object.entries({ '': 'Content-Type: text/plain; charset=UTF-8\nLanguage: fa_IR\nPlural-Forms: nplurals=2; plural=(n > 1);\n', ...translations }).sort(([a], [b]) => Buffer.compare(Buffer.from(a), Buffer.from(b)));
 const count = entries.length;
 const originals = entries.map(([key]) => Buffer.from(key));
 const values = entries.map(([, value]) => Buffer.from(value));
 const originalOffset = 28 + count * 16;
 const valueOffset = originalOffset + originals.reduce((sum, value) => sum + value.length + 1, 0);
 const header = Buffer.alloc(originalOffset);
 [0x950412de, 0, count, 28, 28 + count * 8, 0, 0].forEach((value, index) => header.writeUInt32LE(value, index * 4));
 let originalPosition = originalOffset, valuePosition = valueOffset;
 entries.forEach((_, index) => {
  header.writeUInt32LE(originals[index].length, 28 + index * 8);
  header.writeUInt32LE(originalPosition, 32 + index * 8);
  header.writeUInt32LE(values[index].length, 28 + count * 8 + index * 8);
  header.writeUInt32LE(valuePosition, 32 + count * 8 + index * 8);
  originalPosition += originals[index].length + 1; valuePosition += values[index].length + 1;
 });
 const terminated = (list) => list.flatMap((value) => [value, Buffer.from([0])]);
 writeFileSync(resolve(directory, domain + '-fa_IR.mo'), Buffer.concat([header, ...terminated(originals), ...terminated(values)]));
 const jed = { locale_data: { messages: { '': { domain, lang: 'fa_IR', 'plural-forms': 'nplurals=2; plural=(n > 1);' }, ...Object.fromEntries(Object.entries(translations).map(([key, value]) => [key, [value]])) } } };
 if (domain === 'chidemoon-core') writeFileSync(resolve(directory, domain + '-fa_IR-chidemoon-shop-the-look-editor-script.json'), JSON.stringify(jed));
}
