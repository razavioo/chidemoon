/**
 * Kalahamoon AI Compare — Gutenberg block editor script.
 *
 * Flow:
 *  1. Editor picks 2 products via the shared Kalahamoon picker.
 *  2. Editor clicks "Generate" — we POST to /kalahamoon/v1/ai/compare which proxies
 *     to Kalahamoon's /api/public/ai/compare-products with the stored OAuth token.
 *  3. Response JSON (criteria, pros/cons, verdict, overallWinner) is stored in
 *     block attributes so the frontend renders from cache — no runtime AI calls.
 *  4. Regenerate button hits the same endpoint with ?refresh=1 to bust cache.
 */
(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var Fragment = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var SelectControl = wp.components.SelectControl;
	var Notice = wp.components.Notice;
	var Spinner = wp.components.Spinner;
	var ToggleControl = wp.components.ToggleControl;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;

	function parseIds(str) {
		return (str || '')
			.split(',')
			.map(function (s) { return s.trim(); })
			.filter(Boolean);
	}

	function safeParse(str) {
		if (!str) return null;
		try { return JSON.parse(str); } catch (e) { return null; }
	}

	function AiCompareEditor(props) {
		var a = props.attributes;
		var setAttributes = props.setAttributes;
		var blockProps = useBlockProps();

		var _s1 = useState(false); var loading = _s1[0]; var setLoading = _s1[1];
		var _s2 = useState(null); var error = _s2[0]; var setError = _s2[1];

		var ids = parseIds(a.productIds);
		var comparison = safeParse(a.comparison);
		var products = safeParse(a.products);
		var hasResult = comparison && products;

		function openPicker() {
			if (!window.kalahamoonPicker) return;
			window.kalahamoonPicker.open({
				multiple: true,
				initialIds: ids,
				title: __('Pick exactly 2 products to compare', 'kalahamoon'),
				onSelect: function (selected) {
					// Keep only the first two
					var two = selected.slice(0, 2);
					setAttributes({
						productIds: two.join(','),
						// Clear any previous comparison since inputs changed
						comparison: '',
						products: '',
						generatedAt: '',
					});
				},
			});
		}

		function generate(refresh) {
			if (ids.length !== 2) {
				setError(__('Pick exactly 2 products first.', 'kalahamoon'));
				return;
			}
			setLoading(true);
			setError(null);

			var criteriaArr = a.criteria
				? a.criteria.split(',').map(function (s) { return s.trim(); }).filter(Boolean)
				: [];

			var path = '/kalahamoon/v1/ai/compare' + (refresh ? '?refresh=1' : '');

			// Only include `criteria` when the editor actually supplied values.
			// Sending an empty array fails the panel's `criteria.min(1)` schema
			// with "Too small: expected array to have >=1 items".
			var payload = {
				productIds: ids,
				language: ['fa', 'ar', 'en'].indexOf(a.language) !== -1 ? a.language : 'fa',
			};
			if (criteriaArr.length > 0) {
				payload.criteria = criteriaArr;
			}

			apiFetch({
				path: path,
				method: 'POST',
				data: payload,
			}).then(function (res) {
				setLoading(false);
				if (!res || !res.comparison) {
					setError(__('Unexpected response from Kalahamoon AI.', 'kalahamoon'));
					return;
				}
				setAttributes({
					comparison: JSON.stringify(res.comparison),
					products: JSON.stringify(res.products || []),
					generatedAt: new Date().toISOString(),
				});
			}).catch(function (err) {
				setLoading(false);
				setError((err && err.message) || __('Failed to generate comparison.', 'kalahamoon'));
			});
		}

		// ── Inspector controls ──────────────────────────────────────────────
		var inspector = el(InspectorControls, null,
			el(PanelBody, { title: __('Products', 'kalahamoon'), initialOpen: true },
				el('div', { style: { marginBottom: '12px' } },
					el(Button, {
						variant: 'secondary',
						onClick: openPicker,
						style: { width: '100%', justifyContent: 'center' },
					}, ids.length === 2 ? __('Change products…', 'kalahamoon') : __('Pick 2 products…', 'kalahamoon'))
				),
				ids.length > 0
					? el(TextControl, {
						label: __('Product IDs (comma-separated, exactly 2)', 'kalahamoon'),
						value: a.productIds,
						onChange: function (v) { setAttributes({ productIds: v, comparison: '', products: '', generatedAt: '' }); },
					})
					: null
			),
			el(PanelBody, { title: __('Comparison Criteria', 'kalahamoon'), initialOpen: false },
				el(TextareaControl, {
					label: __('Criteria (comma-separated, optional)', 'kalahamoon'),
					help: __('E.g. "قیمت, کیفیت ساخت, مصرف انرژی". Leave empty and the AI picks.', 'kalahamoon'),
					value: a.criteria,
					onChange: function (v) { setAttributes({ criteria: v, comparison: '', products: '', generatedAt: '' }); },
				}),
				el(SelectControl, {
					label: __('Language', 'kalahamoon'),
					value: a.language,
					options: [
						{ label: __('Persian', 'kalahamoon'), value: 'fa' },
						{ label: __('Arabic', 'kalahamoon'), value: 'ar' },
						{ label: __('English', 'kalahamoon'), value: 'en' },
					],
					onChange: function (v) { setAttributes({ language: v, comparison: '', products: '', generatedAt: '' }); },
				})
			),
			el(PanelBody, { title: __('CTA', 'kalahamoon'), initialOpen: false },
				el(TextControl, {
					label: __('Button text', 'kalahamoon'),
					value: a.ctaText,
					onChange: function (v) { setAttributes({ ctaText: v }); },
				})
			),
			el(PanelBody, { title: __('Sections to show', 'kalahamoon'), initialOpen: false },
				el(ToggleControl, {
					label: __('Product heads row', 'kalahamoon'),
					checked: a.showHeads !== false,
					onChange: function (v) { setAttributes({ showHeads: v }); },
				}),
				el(ToggleControl, {
					label: __('Criteria table', 'kalahamoon'),
					checked: a.showCriteria !== false,
					onChange: function (v) { setAttributes({ showCriteria: v }); },
				}),
				el(ToggleControl, {
					label: __('Pros / cons columns', 'kalahamoon'),
					checked: a.showProsCons !== false,
					onChange: function (v) { setAttributes({ showProsCons: v }); },
				}),
				el(ToggleControl, {
					label: __('Verdict paragraph', 'kalahamoon'),
					checked: a.showVerdict !== false,
					onChange: function (v) { setAttributes({ showVerdict: v }); },
				})
			),
		);

		// ── Empty state ─────────────────────────────────────────────────────
		if (ids.length !== 2) {
			return el('div', blockProps, inspector,
				el('div', {
					style: {
						padding: '40px 24px',
						textAlign: 'center',
						background: '#f9fafb',
						border: '2px dashed #e5e7eb',
						borderRadius: '12px',
						color: '#6b7280',
					},
				},
					el('p', { style: { fontSize: '15px', marginBottom: '6px' } },
						'🤖 ' + __('AI Product Comparison', 'kalahamoon')
					),
					el('p', { style: { fontSize: '13px', marginBottom: '16px' } },
						__('Pick exactly 2 products. AI will generate pros, cons, per-criterion winners, and a verdict.', 'kalahamoon')
					),
					el(Button, { variant: 'primary', onClick: openPicker },
						__('Pick 2 products…', 'kalahamoon')
					)
				)
			);
		}

		// ── Ready to generate, or has a result ──────────────────────────────
		var header = el('div', { className: 'kalahamoon-ai-cmp-editor-header' },
			el('div', { style: { display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' } },
				el('span', { className: 'kalahamoon-ai-cmp-badge' }, '🤖 ' + __('AI Comparison', 'kalahamoon')),
				a.generatedAt
					? el('span', { className: 'kalahamoon-ai-cmp-timestamp' },
						__('Generated', 'kalahamoon') + ': ' + new Date(a.generatedAt).toLocaleString()
					)
					: null
			),
			el('div', { style: { display: 'flex', gap: '6px' } },
				el(Button, {
					variant: hasResult ? 'secondary' : 'primary',
					isBusy: loading,
					disabled: loading,
					onClick: function () { generate(false); },
				}, hasResult ? __('Regenerate', 'kalahamoon') : __('Generate', 'kalahamoon')),
				hasResult
					? el(Button, {
						variant: 'tertiary',
						isBusy: loading,
						disabled: loading,
						onClick: function () { generate(true); },
					}, __('Force refresh', 'kalahamoon'))
					: null
			)
		);

		var errorNotice = error
			? el(Notice, { status: 'error', isDismissible: true, onRemove: function () { setError(null); } }, error)
			: null;

		var loadingBox = loading
			? el('div', { className: 'kalahamoon-ai-cmp-loading' },
				el(Spinner, null),
				el('p', null, __('Calling Kalahamoon AI — this can take 10–20 seconds…', 'kalahamoon'))
			)
			: null;

		var preview = null;
		if (hasResult && !loading) {
			preview = renderPreview(comparison, products, a.ctaText);
		} else if (!hasResult && !loading) {
			preview = el('div', { className: 'kalahamoon-ai-cmp-empty-result' },
				el('p', null, __('Click “Generate” to call Kalahamoon AI and build the comparison.', 'kalahamoon'))
			);
		}

		return el('div', blockProps, inspector,
			el('div', { className: 'kalahamoon-ai-cmp-editor' },
				header,
				errorNotice,
				loadingBox,
				preview
			)
		);
	}

	function renderPreview(cmp, products, ctaText) {
		var p1 = products[0] || {};
		var p2 = products[1] || {};
		var winnerBadge = function (n) {
			return el('span', { className: 'kalahamoon-ai-cmp-winner-badge' },
				'🏆 ' + (n === 1 ? (p1.title || 'Product 1') : (p2.title || 'Product 2'))
			);
		};

		return el('div', { className: 'kalahamoon-ai-cmp-preview' },
			// Product heads
			el('div', { className: 'kalahamoon-ai-cmp-heads' },
				renderHead(p1, cmp.overallWinner === 1),
				renderHead(p2, cmp.overallWinner === 2)
			),
			// Criteria table
			Array.isArray(cmp.criteria) && cmp.criteria.length
				? el('table', { className: 'kalahamoon-ai-cmp-criteria' },
					el('tbody', null,
						cmp.criteria.map(function (c, idx) {
							return el('tr', { key: idx, className: 'kalahamoon-ai-cmp-crit-row' },
								el('td', { className: 'kalahamoon-ai-cmp-crit-name' }, c.name || ''),
								el('td', { className: 'kalahamoon-ai-cmp-crit-score' + (c.winner === 1 ? ' is-winner' : '') },
									c.product1Score || '—'
								),
								el('td', { className: 'kalahamoon-ai-cmp-crit-score' + (c.winner === 2 ? ' is-winner' : '') },
									c.product2Score || '—'
								)
							);
						})
					)
				)
				: null,
			// Pros & cons
			el('div', { className: 'kalahamoon-ai-cmp-pc-cols' },
				renderProsCons(p1.title, cmp.product1Pros, cmp.product1Cons),
				renderProsCons(p2.title, cmp.product2Pros, cmp.product2Cons)
			),
			// Verdict
			cmp.verdict
				? el('div', { className: 'kalahamoon-ai-cmp-verdict' },
					cmp.overallWinner ? winnerBadge(cmp.overallWinner) : null,
					el('p', null, cmp.verdict)
				)
				: null
		);
	}

	function renderHead(p, isWinner) {
		return el('div', { className: 'kalahamoon-ai-cmp-head' + (isWinner ? ' is-winner' : '') },
			p.imageUrl ? el('img', { src: p.imageUrl, alt: p.title || '' }) : null,
			el('div', { className: 'kalahamoon-ai-cmp-head-title' }, p.title || ''),
			p.price ? el('div', { className: 'kalahamoon-ai-cmp-head-price' }, p.price + ' ' + (p.currency === 'IRR' ? 'تومان' : (p.currency || ''))) : null
		);
	}

	function renderProsCons(title, pros, cons) {
		return el('div', { className: 'kalahamoon-ai-cmp-pc-col' },
			el('h4', null, title || ''),
			Array.isArray(pros) && pros.length
				? el('ul', { className: 'kalahamoon-ai-cmp-pros' }, pros.map(function (p, i) {
					return el('li', { key: 'p' + i }, p);
				}))
				: null,
			Array.isArray(cons) && cons.length
				? el('ul', { className: 'kalahamoon-ai-cmp-cons' }, cons.map(function (c, i) {
					return el('li', { key: 'c' + i }, c);
				}))
				: null
		);
	}

	registerBlockType('kalahamoon/ai-compare', {
		edit: AiCompareEditor,
	});
})(window.wp);
