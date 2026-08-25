/**
 * Kalahamoon AI Image Studio — admin app.
 *
 * Modes:
 *  - enhance:    improve lighting/background of the product's existing photo
 *  - background: place the product into a new staged scene (prompt)
 *  - aggregate:  combine several product images into one staged image
 *  - generate:   text-to-image from a prompt only
 *
 * Flow: pick a product (or rely on prompt for "generate") → Generate → review
 * before/after → Save to Media Library, optionally applying it to the product.
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.element) return;

	var el = wp.element.createElement;
	var render = wp.element.render || wp.element.createRoot;
	var useState = wp.element.useState;
	var Fragment = wp.element.Fragment;
	var Button = wp.components.Button;
	var TextareaControl = wp.components.TextareaControl;
	var SelectControl = wp.components.SelectControl;
	var RadioControl = wp.components.RadioControl;
	var Spinner = wp.components.Spinner;
	var Notice = wp.components.Notice;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;
	var studioConfig = window.kalahamoonStudioConfig || {};
	var localeLanguage = String(studioConfig.language || document.documentElement.lang || 'fa').toLowerCase().split('-')[0];
	if (['fa', 'ar', 'en'].indexOf(localeLanguage) === -1) localeLanguage = 'fa';

	var MODES = [
		{ label: __('Enhance existing image', 'kalahamoon'), value: 'enhance' },
		{ label: __('Background / scene swap', 'kalahamoon'), value: 'background' },
		{ label: __('Combine images into one', 'kalahamoon'), value: 'aggregate' },
		{ label: __('Generate from prompt', 'kalahamoon'), value: 'generate' },
	];

	function Studio() {
		var s1 = useState('enhance'); var mode = s1[0]; var setMode = s1[1];
		var s2 = useState(''); var productId = s2[0]; var setProductId = s2[1];
		var s3 = useState(''); var prompt = s3[0]; var setPrompt = s3[1];
		var s4 = useState('1024x1024'); var size = s4[0]; var setSize = s4[1];
		var s5 = useState(false); var loading = s5[0]; var setLoading = s5[1];
		var s6 = useState(null); var error = s6[0]; var setError = s6[1];
		var s7 = useState(null); var result = s7[0]; var setResult = s7[1];
		var s8 = useState(''); var notice = s8[0]; var setNotice = s8[1];
		var s9 = useState(false); var saving = s9[0]; var setSaving = s9[1];

		var needsProduct = mode !== 'generate';

		function openPicker() {
			if (!window.kalahamoonPicker) return;
			window.kalahamoonPicker.open({
				multiple: false,
				initialIds: productId ? [productId] : [],
				title: __('Select a product', 'kalahamoon'),
				onSelect: function (id) {
					setProductId(Array.isArray(id) ? id[0] : id);
					setResult(null);
				},
			});
		}

		function generate() {
			setError(null);
			setNotice('');
			if (needsProduct && !productId) {
				setError(__('Pick a product first.', 'kalahamoon'));
				return;
			}
			if (mode === 'generate' && !prompt.trim()) {
				setError(__('Write a prompt for generate mode.', 'kalahamoon'));
				return;
			}

			setLoading(true);
			setResult(null);

			var data = { mode: mode, size: size, language: localeLanguage };
			if (productId) data.productId = productId;
			if (prompt.trim()) data.prompt = prompt.trim();

			apiFetch({ path: '/kalahamoon/v1/ai/generate-image', method: 'POST', data: data })
				.then(function (res) {
					setLoading(false);
					if (!res || !res.images || !res.images.length) {
						setError(__('No image was returned. Try again or adjust the prompt.', 'kalahamoon'));
						return;
					}
					setResult(res);
				})
				.catch(function (err) {
					setLoading(false);
					setError((err && err.message) || __('Image generation failed.', 'kalahamoon'));
				});
		}

		function save(imageUrl, apply) {
			setSaving(true);
			setNotice('');
			setError(null);
			apiFetch({
				path: '/kalahamoon/v1/ai/save-image',
				method: 'POST',
				data: {
					imageUrl: imageUrl,
					productId: productId,
					applyToProduct: !!apply,
					provenance: result && result.provenance ? result.provenance : {}
				},
			}).then(function (res) {
				setSaving(false);
				if (res && res.ok) {
					setNotice(apply && res.applied
						? __('Saved to Media Library and applied to the product.', 'kalahamoon')
						: __('Saved to your Media Library.', 'kalahamoon'));
				} else {
					setError(__('Could not save the image.', 'kalahamoon'));
				}
			}).catch(function (err) {
				setSaving(false);
				setError((err && err.message) || __('Could not save the image.', 'kalahamoon'));
			});
		}

		// ── Controls ────────────────────────────────────────────────────────
		var controls = el('div', { className: 'kalahamoon-studio-controls', style: ctrlStyle },
			el(RadioControl, {
				label: __('Mode', 'kalahamoon'),
				selected: mode,
				options: MODES,
				onChange: function (v) { setMode(v); setResult(null); },
			}),
			needsProduct
				? el('div', { style: { margin: '12px 0' } },
					el(Button, { variant: 'secondary', onClick: openPicker },
						productId ? __('Change product…', 'kalahamoon') : __('Select product…', 'kalahamoon')
					),
					productId
						? el('code', { style: codeStyle }, productId)
						: null
				)
				: null,
			el(TextareaControl, {
				label: mode === 'generate' ? __('Prompt (required)', 'kalahamoon') : __('Extra direction (optional)', 'kalahamoon'),
				help: mode === 'aggregate'
					? __('Describe the staged arrangement you want from the product images.', 'kalahamoon')
					: __('Optional notes to steer lighting, scene, or style.', 'kalahamoon'),
				value: prompt,
				onChange: setPrompt,
				rows: 3,
			}),
			el(SelectControl, {
				label: __('Aspect / size', 'kalahamoon'),
				value: size,
				options: [
					{ label: __('Square (1024×1024)', 'kalahamoon'), value: '1024x1024' },
					{ label: __('Portrait (1024×1536)', 'kalahamoon'), value: '1024x1536' },
					{ label: __('Landscape (1536×1024)', 'kalahamoon'), value: '1536x1024' },
				],
				onChange: setSize,
			}),
			el(Button, {
				variant: 'primary',
				isBusy: loading,
				disabled: loading,
				onClick: generate,
				style: { marginTop: '8px' },
			}, loading ? __('Generating…', 'kalahamoon') : __('Generate image', 'kalahamoon'))
		);

		// ── Result ──────────────────────────────────────────────────────────
		var resultView = null;
		if (loading) {
			resultView = el('div', { style: { display: 'flex', alignItems: 'center', gap: '10px', padding: '24px' } },
				el(Spinner, null),
				el('span', null, __('Calling Kalahamoon AI — this can take 10–30 seconds…', 'kalahamoon'))
			);
		} else if (result) {
			var before = (result.sourceImageUrls && result.sourceImageUrls[0]) || '';
			resultView = el('div', { style: { marginTop: '16px' } },
				el('div', { style: { display: 'flex', gap: '16px', flexWrap: 'wrap' } },
					before
						? el('figure', { style: figStyle },
							el('figcaption', { style: capStyle }, __('Before', 'kalahamoon')),
							el('img', { src: before, style: imgStyle })
						)
						: null,
					el('figure', { style: figStyle },
						el('figcaption', { style: capStyle }, __('After', 'kalahamoon')),
						el('img', { src: result.images[0], style: imgStyle })
					)
				),
				el('div', { style: { display: 'flex', gap: '8px', marginTop: '12px', flexWrap: 'wrap' } },
					el(Button, { variant: 'secondary', isBusy: saving, disabled: saving, onClick: function () { save(result.images[0], false); } },
						__('Save to Media Library', 'kalahamoon')
					),
					productId
						? el(Button, { variant: 'primary', isBusy: saving, disabled: saving, onClick: function () { save(result.images[0], true); } },
							__('Save & set as product image', 'kalahamoon')
						)
						: null
				)
			);
		}

		return el(Fragment, null,
			error ? el(Notice, { status: 'error', isDismissible: true, onRemove: function () { setError(null); } }, error) : null,
			notice ? el(Notice, { status: 'success', isDismissible: true, onRemove: function () { setNotice(''); } }, notice) : null,
			el('div', { style: { display: 'flex', gap: '24px', flexWrap: 'wrap', alignItems: 'flex-start', marginTop: '12px' } },
				el('div', { style: { flex: '0 0 320px', maxWidth: '360px' } }, controls),
				el('div', { style: { flex: 1, minWidth: '320px' } }, resultView)
			)
		);
	}

	var ctrlStyle = { background: '#fff', border: '1px solid #e2e4e7', borderRadius: '8px', padding: '16px' };
	var codeStyle = { display: 'inline-block', marginInlineStart: '8px', fontSize: '11px', background: '#f3f4f6', padding: '2px 6px', borderRadius: '4px' };
	var figStyle = { margin: 0, flex: '0 0 240px', maxWidth: '260px' };
	var capStyle = { fontSize: '12px', fontWeight: 600, color: '#6b7280', marginBottom: '6px' };
	var imgStyle = { width: '100%', height: 'auto', borderRadius: '8px', border: '1px solid #e5e7eb', display: 'block' };

	function mount() {
		var node = document.getElementById('kalahamoon-ai-studio-root');
		if (!node) return;
		if (wp.element.createRoot) {
			wp.element.createRoot(node).render(el(Studio));
		} else {
			wp.element.render(el(Studio), node);
		}
	}

	if (document.readyState !== 'loading') {
		mount();
	} else {
		document.addEventListener('DOMContentLoaded', mount);
	}
})(window.wp);
