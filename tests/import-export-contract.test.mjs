import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

import {
	buildExport,
	canonicalJson,
	sha256Hex,
	verifyChecksum,
	cleanText,
	normalizeDatetimeToAtom,
	isAllowedAffiliateUrl,
	validateRecord,
} from '../tools/build-import-export.mjs';

const TOOL_PATH = fileURLToPath(new URL('../tools/build-import-export.mjs', import.meta.url));

function referenceCanonical(value) {
	if (value === null) return 'null';
	const type = typeof value;
	if (type === 'string') return JSON.stringify(value);
	if (type === 'number') return Number.isFinite(value) ? JSON.stringify(value) : 'null';
	if (type === 'boolean') return value ? 'true' : 'false';
	if (Array.isArray(value)) {
		const parts = [];
		for (const entry of value) parts.push(referenceCanonical(entry));
		return `[${parts.join(',')}]`;
	}
	if (type === 'object') {
		const keys = Object.keys(value);
		keys.sort((a, b) => {
			const left = Buffer.from(a, 'utf8');
			const right = Buffer.from(b, 'utf8');
			return left.compare(right);
		});
		const members = keys.map((key) => `${JSON.stringify(key)}:${referenceCanonical(value[key])}`);
		return `{${members.join(',')}}`;
	}
	return 'null';
}

const VALID_RECORD = {
	sourceKey: 'sku:abc-123',
	title: 'کتری برقی مدل X200',
	description: '<p>بویلر استیل</p>',
	merchant: {
		sellerName: 'دیجی‌مارکت',
		platform: 'digimarket',
		url: 'https://shop.example.com/p/x200',
		sourceCheckedAt: '2026-08-20T09:30:00Z',
	},
	category: { label: 'آشپزخانه', slug: 'kitchen' },
	imageUrl: 'https://cdn.example.com/img/x200.webp',
	status: 'reviewed',
	specs: [
		['توان', '2000W'],
	],
	price: '2450000',
	currency: 'IRT',
};

describe('canonical JSON contract', () => {
	it('matches the importer canonical_json algorithm on nested data', () => {
		const sample = {
			zeta: 1,
			alpha: { b: ['x/y', 'پارسی', true], a: null },
			list: [{ k: 'v', n: 2.5 }, []],
			esc: 'quote"backslash\\newline\n',
		};
		assert.equal(canonicalJson(sample), referenceCanonical(sample));
	});

	it('sorts object keys byte-wise and keeps arrays ordered', () => {
		assert.equal(
			canonicalJson({ b: 1, a: 2, A: 3 }),
			'{"A":3,"a":2,"b":1}',
		);
		assert.equal(canonicalJson([3, 1, 2]), '[3,1,2]');
	});
});

describe('buildExport artifact', () => {
	const artifact = buildExport({
		records: [VALID_RECORD],
		organizationSlug: 'chidemoon',
		generatedAt: '2026-08-26T00:00:00Z',
	});

	it('carries schema v1 envelope fields', () => {
		assert.equal(artifact.schemaVersion, 1);
		assert.deepEqual(artifact.organization, { slug: 'chidemoon' });
		assert.equal(artifact.generatedAt, '2026-08-26T00:00:00+00:00');
		assert.equal(artifact.items.length, 1);
		assert.equal(artifact.skipped.length, 0);
	});

	it('produces a checksum the importer algorithm would verify', () => {
		assert.match(artifact.checksum, /^[a-f0-9]{64}$/);
		const body = { ...artifact };
		delete body.checksum;
		assert.equal(sha256Hex(referenceCanonical(body)), artifact.checksum);
		assert.equal(verifyChecksum(artifact), true);
	});

	it('checksum changes when any payload field is tampered with', () => {
		const tampered = JSON.parse(JSON.stringify(artifact));
		tampered.items[0].title = 'تغییر یافته';
		const body = { ...tampered };
		delete body.checksum;
		assert.notEqual(sha256Hex(referenceCanonical(body)), tampered.checksum);
	});
});

describe('record validation vocabulary', () => {
	it('accepts a fully valid record without issues', () => {
		const verdict = validateRecord(VALID_RECORD);
		assert.deepEqual(verdict.issues, []);
		assert.equal(verdict.sourceKey, 'sku:abc-123');
		assert.equal(verdict.reviewState, 'reviewed');
	});

	it('rejects non-object records like the importer does', () => {
		assert.deepEqual(validateRecord('nope').issues, ['record_not_object']);
		assert.deepEqual(validateRecord(null).issues, ['record_not_object']);
	});

	it('emits the exact issue codes for each failure mode', () => {
		const cases = [
			[{ ...VALID_RECORD, title: '   ' }, ['missing_title']],
			[{ ...VALID_RECORD, sourceKey: 'bad key!' }, ['invalid_source_key']],
			[
				{ ...VALID_RECORD, merchant: { ...VALID_RECORD.merchant, url: 'http://localhost/p/x200' } },
				['invalid_affiliate_url'],
			],
			[{ ...VALID_RECORD, category: { label: '', slug: '' } }, ['missing_category']],
			[
				{ ...VALID_RECORD, merchant: { ...VALID_RECORD.merchant, sourceCheckedAt: 'not-a-date' } },
				['missing_or_invalid_source_checked_at'],
			],
			[
				{ ...VALID_RECORD, merchant: { sellerName: 'x', url: '', sourceCheckedAt: '2026-01-01T00:00:00Z' } },
				['invalid_affiliate_url', 'missing_or_invalid_source_url'],
			],
			[{ ...VALID_RECORD, imageUrl: 'http://cdn.example.com/a.png' }, ['unsafe_image_url']],
			[{ ...VALID_RECORD, status: 'quarantine' }, ['source_marked_quarantine']],
			[{ ...VALID_RECORD, specs: 'not-an-array' }, ['invalid_facts']],
			[{ ...VALID_RECORD, price: 'abc' }, ['invalid_price']],
			[{ ...VALID_RECORD, merchant: undefined }, ['invalid_affiliate_url', 'missing_or_invalid_source_url', 'missing_or_invalid_source_checked_at']],
		];

		for (const [record, expected] of cases) {
			const verdict = validateRecord(record);
			assert.deepEqual([...verdict.issues].sort(), [...expected].sort(), `for ${JSON.stringify(record).slice(0, 80)}`);
		}
	});

	it('blocks private and loopback IP literals but allows public hosts', () => {
		assert.equal(isAllowedAffiliateUrl('http://127.0.0.1/go'), false);
		assert.equal(isAllowedAffiliateUrl('http://192.168.1.10/go'), false);
		assert.equal(isAllowedAffiliateUrl('http://10.0.0.5/go'), false);
		assert.equal(isAllowedAffiliateUrl('http://[::1]/go'), false);
		assert.equal(isAllowedAffiliateUrl('http://shop.example.internal/go'), false);
		assert.equal(isAllowedAffiliateUrl('http://shop.example.local/go'), false);
		assert.equal(isAllowedAffiliateUrl('https://93.184.216.34/p'), true);
		assert.equal(isAllowedAffiliateUrl('https://shop.example.com/p'), true);
	});
});

describe('normalization helpers mirroring the importer', () => {
	it('truncates cleaned text by code points', () => {
		assert.equal(cleanText('<b>سلام</b>\tجهان', 255), 'سلام جهان');
		const long = Array.from({ length: 300 }, (_, i) => String(i % 10)).join('');
		assert.equal(Array.from(cleanText(long, 255)).length, 255);
	});

	it('formats timestamps as UTC DATE_ATOM with +00:00 offset', () => {
		assert.equal(normalizeDatetimeToAtom('2026-08-20T09:30:00Z'), '2026-08-20T09:30:00+00:00');
		assert.equal(normalizeDatetimeToAtom('2026-08-20T12:00:00+03:30'), '2026-08-20T08:30:00+00:00');
		assert.equal(normalizeDatetimeToAtom('garbage'), '');
		assert.equal(normalizeDatetimeToAtom(''), '');
	});

	it('maps legacy review states onto reviewed/draft/quarantine', () => {
		// Strict: only an explicit "reviewed" promotes. Legacy aliases that
		// previously auto-published now stay draft for human review.
		assert.equal(validateRecord({ ...VALID_RECORD, status: 'reviewed' }).reviewState, 'reviewed');
		assert.equal(validateRecord({ ...VALID_RECORD, status: 'verified' }).reviewState, 'draft');
		assert.equal(validateRecord({ ...VALID_RECORD, status: 'published' }).reviewState, 'draft');
		assert.equal(validateRecord({ ...VALID_RECORD, status: 'weird-state' }).reviewState, 'draft');
	});

	it('moves invalid and duplicate records to skipped during export build', () => {
		const duplicate = { ...VALID_RECORD, title: 'نسخه تکراری' };
		const broken = { ...VALID_RECORD, sourceKey: '!!!' };
		const artifact = buildExport({
			records: [VALID_RECORD, duplicate, broken],
			organizationSlug: 'chidemoon',
			generatedAt: '2026-08-26T00:00:00Z',
		});
		assert.equal(artifact.items.length, 1);
		assert.equal(artifact.skipped.length, 2);
		assert.ok(artifact.skipped.some((entry) => entry.issues.includes('duplicate_source_key_in_export')));
		assert.ok(artifact.skipped.some((entry) => entry.issues.includes('invalid_source_key')));
	});
});

describe('CLI end-to-end', () => {
	let workspace;

	function runTool(args) {
		return spawnSync(process.execPath, [TOOL_PATH, ...args], { encoding: 'utf8' });
	}

	function setup() {
		workspace = mkdtempSync(join(tmpdir(), 'chidemoon-export-'));
	}

	function teardown() {
		rmSync(workspace, { recursive: true, force: true });
	}

	it('writes a verifiable export for a valid catalog', () => {
		setup();
		try {
			const catalogPath = join(workspace, 'catalog.json');
			const outputPath = join(workspace, 'products.export.json');
			writeFileSync(catalogPath, JSON.stringify({ items: [VALID_RECORD] }), 'utf8');

			const result = runTool([
				'--input', catalogPath,
				'--output', outputPath,
				'--organization-slug', 'chidemoon',
			]);
			assert.equal(result.status, 0, result.stderr);

			const artifact = JSON.parse(readFileSync(outputPath, 'utf8'));
			assert.equal(artifact.schemaVersion, 1);
			assert.equal(verifyChecksum(artifact), true);
		} finally {
			teardown();
		}
	});

	it('fails when catalog organization disagrees with --organization-slug', () => {
		setup();
		try {
			const catalogPath = join(workspace, 'catalog.json');
			writeFileSync(
				catalogPath,
				JSON.stringify({ organization: { slug: 'other-org' }, items: [VALID_RECORD] }),
				'utf8',
			);
			const result = runTool(['--input', catalogPath, '--organization-slug', 'chidemoon']);
			assert.notEqual(result.status, 0);
			assert.match(result.stderr, /Organization mismatch/);
		} finally {
			teardown();
		}
	});

	it('fails fast on malformed catalogs and missing input', () => {
		setup();
		try {
			const badPath = join(workspace, 'bad.json');
			writeFileSync(badPath, '{ not json', 'utf8');
			assert.notEqual(runTool(['--input', badPath]).status, 0);
			assert.notEqual(runTool([]).status, 0);
			assert.notEqual(runTool(['--input', join(workspace, 'missing.json')]).status, 0);
		} finally {
			teardown();
		}
	});
});
