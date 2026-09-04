#!/usr/bin/env node
/**
 * Builds a signed offline product export consumable by
 * `wp chidemoon import-products` (Chidemoon_Core_Importer, schema version 1).
 *
 * Usage:
 *   node tools/build-import-export.mjs --input catalog.json \
 *        --output products.export.json --organization-slug chidemoon [--generated-at <ISO>]
 *
 * Records failing validation are moved to "skipped" with the same issue-code
 * vocabulary the importer uses. Image URLs are checked statically here; the
 * importer re-verifies them (including DNS-level SSRF guards) at import time,
 * keeping this tool fully offline-capable.
 */

import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import { parseArgs } from 'node:util';
import { pathToFileURL } from 'node:url';
import process from 'node:process';

export const EXPORT_SCHEMA_VERSION = 1;
export const DEFAULT_ORGANIZATION_SLUG = 'chidemoon';
export const MAX_FILE_SIZE = 52428800;

const SOURCE_KEY_PATTERN = /^[A-Za-z0-9._:-]{1,190}$/;
const ORGANIZATION_SLUG_PATTERN = /^[a-z0-9][a-z0-9-]{0,62}$/;

export function sha256Hex(text) {
	return createHash('sha256').update(text, 'utf8').digest('hex');
}

export function canonicalJson(value) {
	if (
		value === null ||
		typeof value === 'boolean' ||
		typeof value === 'number' ||
		typeof value === 'string'
	) {
		return typeof value === 'number' && !Number.isFinite(value) ? 'null' : JSON.stringify(value);
	}
	if (Array.isArray(value)) {
		return `[${value.map(canonicalJson).join(',')}]`;
	}
	if (typeof value === 'object') {
		const keys = Object.keys(value).sort(compareBytes);
		const members = keys.map((key) => `${JSON.stringify(key)}:${canonicalJson(value[key])}`);
		return `{${members.join(',')}}`;
	}
	return 'null';
}

function compareBytes(a, b) {
	return a < b ? -1 : a > b ? 1 : 0;
}

export function cleanText(value, length) {
	if (typeof value !== 'string' && typeof value !== 'number' && typeof value !== 'boolean') {
		return '';
	}
	let text = String(value);
	text = text.replace(/[\u0000-\u001F\u007F]/g, ' ');
	text = text.replace(/<[^>]*>/g, ' ');
	text = text.replace(/\s+/g, ' ').trim();
	const characters = Array.from(text);
	return characters.slice(0, length).join('');
}

export function normalizeDatetimeToAtom(value) {
	if (typeof value !== 'string' || '' === value.trim()) {
		return '';
	}
	const milliseconds = Date.parse(value);
	if (Number.isNaN(milliseconds)) {
		return '';
	}
	const date = new Date(milliseconds);
	const pad = (part) => String(part).padStart(2, '0');
	return (
		`${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}` +
		`T${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}:${pad(date.getUTCSeconds())}+00:00`
	);
}

function isDisallowedHostSuffix(host) {
	return 'localhost' === host || host.endsWith('.local') || host.endsWith('.internal');
}

function publicIpLiteralCheck(host) {
	const ipv4 = /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/.exec(host);
	if (ipv4) {
		const octets = ipv4.slice(1).map(Number);
		if (octets.some((octet) => octet > 255)) {
			return false;
		}
		const [first, second] = octets;
		if (0 === first || 10 === first || 127 === first) return false;
		if (100 === first && second >= 64 && second <= 127) return false;
		if (169 === first && 254 === second) return false;
		if (172 === first && second >= 16 && second <= 31) return false;
		if (192 === first && 168 === second) return false;
		if (first >= 224) return false;
		return true;
	}
	if (host.includes(':')) {
		const lower = host.toLowerCase();
		if ('::' === lower || '::1' === lower) return false;
		if (lower.startsWith('fc') || lower.startsWith('fd')) return false;
		if (/^fe[89ab]/.test(lower)) return false;
		if (lower.startsWith('ff')) return false;
		if (lower.startsWith('2001:db8')) return false;
		return true;
	}
	return null;
}

function hostIsAllowed(url) {
	let parsed;
	try {
		parsed = new URL(url);
	} catch {
		return false;
	}
	const scheme = parsed.protocol.replace(/:$/, '').toLowerCase();
	if (!['http', 'https'].includes(scheme)) {
		return false;
	}
	let host = parsed.hostname.toLowerCase();
	if (host.startsWith('[') && host.endsWith(']')) {
		host = host.slice(1, -1);
	}
	if ('' === host || isDisallowedHostSuffix(host)) {
		return false;
	}
	const ipVerdict = publicIpLiteralCheck(host);
	if (null !== ipVerdict) {
		return ipVerdict;
	}
	return true;
}

export function isAllowedAffiliateUrl(url) {
	return hostIsAllowed(url);
}

export function isSafeHttpsImageUrlStatic(url) {
	let parsed;
	try {
		parsed = new URL(url);
	} catch {
		return false;
	}
	if ('https:' !== parsed.protocol) {
		return false;
	}
	if (parsed.username || parsed.password) {
		return false;
	}
	if (parsed.port && '443' !== parsed.port) {
		return false;
	}
	return isAllowedAffiliateUrl(parsed.toString());
}

function resolveImageUrl(record) {
	let image = typeof record?.imageUrl === 'string' ? record.imageUrl.trim() : '';
	if (('' === image || null === image) && Array.isArray(record?.gallery)) {
		const first = record.gallery[0];
		image = typeof first === 'string' ? first.trim() : '';
	}
	return image;
}

function normalizePriceCandidate(price) {
	if (null === price || undefined === price) {
		return { kind: 'empty' };
	}
	if (typeof price !== 'string' && typeof price !== 'number') {
		return { kind: 'invalid' };
	}
	const text = String(price).trim();
	if ('' === text) {
		return { kind: 'empty' };
	}
	if (!/^\d+(?:[.,]\d+)?$/.test(text)) {
		return { kind: 'invalid' };
	}
	const value = Number(text.replace(',', '.'));
	if (!Number.isFinite(value) || value < 0) {
		return { kind: 'invalid' };
	}
	return { kind: 'ok', value: text.replace(',', '.') };
}

function normalizeReviewState(status) {
	const state = cleanText(status, 64).toLowerCase().replace(/[^a-z0-9_-]/g, '');
	// Strict parity with Chidemoon_Core_Importer::normalize_review_state():
	// only an explicit "reviewed" promotes; legacy aliases stay draft.
	if ('reviewed' === state) {
		return 'reviewed';
	}
	if ('quarantine' === state) {
		return 'quarantine';
	}
	return 'draft';
}

export function validateRecord(record) {
	const issues = [];
	if (null === record || undefined === record || typeof record !== 'object' || Array.isArray(record)) {
		return { issues: ['record_not_object'], sourceKey: '', reviewState: 'draft' };
	}

	const rawSourceKey = typeof record.sourceKey === 'string' ? record.sourceKey.trim() : '';
	const sourceKey = SOURCE_KEY_PATTERN.test(rawSourceKey) ? rawSourceKey : '';
	if ('' === sourceKey) {
		issues.push('invalid_source_key');
	}

	const title = cleanText(record.title, 255);
	if ('' === title) {
		issues.push('missing_title');
	}

	const merchant = record.merchant && typeof record.merchant === 'object' && !Array.isArray(record.merchant)
		? record.merchant
		: {};
	const merchantUrl = typeof merchant.url === 'string' ? merchant.url : '';
	const affiliateUrlOk =
		'' !== merchantUrl &&
		(() => {
			try {
				const parsed = new URL(merchantUrl);
				return ['http:', 'https:'].includes(parsed.protocol) && isAllowedAffiliateUrl(merchantUrl);
			} catch {
				return false;
			}
		})();
	if (!affiliateUrlOk) {
		issues.push('invalid_affiliate_url');
	}
	let sourceUrlOk = false;
	try {
		const parsedSource = new URL(merchantUrl);
		sourceUrlOk = ['http:', 'https:'].includes(parsedSource.protocol) && '' !== parsedSource.hostname;
	} catch {
		sourceUrlOk = false;
	}
	if (!sourceUrlOk) {
		issues.push('missing_or_invalid_source_url');
	}
	if ('' === normalizeDatetimeToAtom(typeof merchant.sourceCheckedAt === 'string' ? merchant.sourceCheckedAt : '')) {
		issues.push('missing_or_invalid_source_checked_at');
	}

	const category = record.category && typeof record.category === 'object' && !Array.isArray(record.category)
		? record.category
		: {};
	const categoryName = cleanText(category.label, 100);
	const categorySlug = cleanText(category.slug !== undefined && category.slug !== null ? category.slug : categoryName, 190)
		.toLowerCase()
		.replace(/[^a-z0-9\x20-_]/g, '')
		.trim()
		.replace(/\x20+/g, '-');
	if ('' === categoryName || '' === categorySlug) {
		issues.push('missing_category');
	}

	const imageUrl = resolveImageUrl(record);
	if ('' !== imageUrl && !isSafeHttpsImageUrlStatic(imageUrl)) {
		issues.push('unsafe_image_url');
	}

	const reviewState = normalizeReviewState(record.status ?? 'draft');
	if ('quarantine' === reviewState) {
		issues.push('source_marked_quarantine');
	}

	if (record.specs !== undefined && record.specs !== null && !Array.isArray(record.specs)) {
		issues.push('invalid_facts');
	}

	const priceVerdict = normalizePriceCandidate(record.price);
	if ('invalid' === priceVerdict.kind) {
		issues.push('invalid_price');
	}

	return { issues: [...new Set(issues)], sourceKey: rawSourceKey, reviewState };
}

export function buildExport({ records, organizationSlug, generatedAt }) {
	const items = [];
	const skipped = [];
	const seenSourceKeys = new Set();

	const sourceRecords = Array.isArray(records) ? records : [];
	sourceRecords.forEach((record, index) => {
		const verdict = validateRecord(record);
		if (verdict.issues.includes('invalid_source_key')) {
			skipped.push({ index, sourceKey: verdict.sourceKey, issues: verdict.issues });
			return;
		}
		if ('' !== verdict.sourceKey && seenSourceKeys.has(verdict.sourceKey)) {
			skipped.push({
				index,
				sourceKey: verdict.sourceKey,
				issues: [...verdict.issues, 'duplicate_source_key_in_export'],
			});
			return;
		}
		if (verdict.issues.length > 0) {
			skipped.push({ index, sourceKey: verdict.sourceKey, issues: verdict.issues });
			return;
		}
		seenSourceKeys.add(verdict.sourceKey);
		items.push(record);
	});

	const resolvedGeneratedAt =
		normalizeDatetimeToAtom(typeof generatedAt === 'string' ? generatedAt : '') ||
		normalizeDatetimeToAtom(new Date().toISOString());

	const body = {
		schemaVersion: EXPORT_SCHEMA_VERSION,
		organization: { slug: organizationSlug },
		generatedAt: resolvedGeneratedAt,
		items,
		skipped,
	};
	const checksum = sha256Hex(canonicalJson(body));

	return {
		...body,
		checksum,
	};
}

export function verifyChecksum(artifact) {
	if (!artifact || typeof artifact !== 'object' || typeof artifact.checksum !== 'string') {
		return false;
	}
	const body = { ...artifact };
	delete body.checksum;
	return sha256Hex(canonicalJson(body)) === artifact.checksum.toLowerCase();
}

function parseCatalog(raw) {
	const parsed = JSON.parse(raw);
	if (Array.isArray(parsed)) {
		return { records: parsed, declaredOrganizationSlug: null, declaredGeneratedAt: null };
	}
	if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
		const records = Array.isArray(parsed.items) ? parsed.items : null;
		if (null === records) {
			throw new Error('Catalog JSON must contain an "items" array (or be a bare array).');
		}
		const slug =
			parsed.organization && typeof parsed.organization === 'object'
				? typeof parsed.organization.slug === 'string'
					? parsed.organization.slug
					: null
				: null;
		return {
			records,
			declaredOrganizationSlug: slug,
			declaredGeneratedAt: typeof parsed.generatedAt === 'string' ? parsed.generatedAt : null,
		};
	}
	throw new Error('Catalog JSON must be an array or an object with an "items" array.');
}

export function main(argv = process.argv) {
	const { values } = parseArgs({
		args: argv.slice(2),
		options: {
			input: { type: 'string' },
			output: { type: 'string' },
			'organization-slug': { type: 'string', default: DEFAULT_ORGANIZATION_SLUG },
			'generated-at': { type: 'string' },
		},
		strict: true,
	});

	if (!values.input) {
		console.error('Usage: node tools/build-import-export.mjs --input <catalog.json> [--output <file>] [--organization-slug <slug>] [--generated-at <ISO>]');
		return 2;
	}

	const organizationSlug = String(values['organization-slug']).toLowerCase();
	if (!ORGANIZATION_SLUG_PATTERN.test(organizationSlug)) {
		console.error(`Invalid --organization-slug: ${values['organization-slug']}`);
		return 2;
	}

	let raw;
	try {
		raw = readFileSync(values.input, 'utf8');
	} catch (error) {
		console.error(`Cannot read input file: ${error.message}`);
		return 2;
	}
	if (Buffer.byteLength(raw, 'utf8') > MAX_FILE_SIZE) {
		console.error('Input file exceeds the 50 MiB import cap.');
		return 2;
	}

	let catalog;
	try {
		catalog = parseCatalog(raw);
	} catch (error) {
		console.error(`Invalid catalog: ${error.message}`);
		return 2;
	}

	if (catalog.declaredOrganizationSlug && catalog.declaredOrganizationSlug.toLowerCase() !== organizationSlug) {
		console.error(
			`Organization mismatch: catalog declares "${catalog.declaredOrganizationSlug}" but --organization-slug is "${organizationSlug}".`,
		);
		return 2;
	}

	const requestedGeneratedAt = values['generated-at'] ?? catalog.declaredGeneratedAt ?? null;
	const generatedAt =
		null !== requestedGeneratedAt
			? normalizeDatetimeToAtom(requestedGeneratedAt)
			: normalizeDatetimeToAtom(new Date().toISOString());
	if ('' === generatedAt) {
		console.error(`Invalid generated-at timestamp: ${requestedGeneratedAt}`);
		return 2;
	}

	const artifact = buildExport({
		records: catalog.records,
		organizationSlug,
		generatedAt,
	});

	const serialized = `${JSON.stringify(artifact, null, '\t')}\n`;
	if (values.output && '-' !== values.output) {
		writeFileSync(values.output, serialized, 'utf8');
	} else {
		process.stdout.write(serialized);
	}

	console.error(
		`schemaVersion=${EXPORT_SCHEMA_VERSION} organization=${organizationSlug} ` +
			`accepted=${artifact.items.length} skipped=${artifact.skipped.length} ` +
			`checksum=${artifact.checksum}`,
	);
	return 0;
}

const invokedDirectly =
	process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
if (invokedDirectly) {
	process.exit(main());
}
