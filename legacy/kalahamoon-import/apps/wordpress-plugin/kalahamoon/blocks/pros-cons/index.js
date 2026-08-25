(function (wp) {
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var SelectControl = wp.components.SelectControl;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	var MAX_ITEMS = 10;

	var ICON_OPTIONS = [
		{ label: '✅', value: '✅' },
		{ label: '❌', value: '❌' },
		{ label: '⚠️', value: '⚠️' },
		{ label: 'ℹ️', value: 'ℹ️' },
		{ label: '➕', value: '➕' },
		{ label: '➖', value: '➖' },
		{ label: '⭐', value: '⭐' },
		{ label: '💡', value: '💡' },
	];

	function parseLine(str) {
		// Support both legacy pipe-separated and newline-separated storage.
		// Do NOT filter empty strings — that's what was hiding newly-added blank items.
		// Do NOT trim here: trimming on every keystroke render strips the space the
		// user just typed, making it impossible to type spaces in the item fields.
		// Whitespace is normalised on the front end (render.php) instead.
		return (str || '').split(/[|\n]/);
	}

	function serializeLine(arr) {
		return arr.join('\n');
	}

	// Each entry is stored as "icon::label" or just "label" for legacy data.
	function parseEntry(raw) {
		var sep = raw.indexOf('::');
		if (sep !== -1) {
			return { icon: raw.slice(0, sep), label: raw.slice(sep + 2) };
		}
		return { icon: '', label: raw };
	}

	function serializeEntry(icon, label) {
		return icon ? (icon + '::' + label) : label;
	}

	function ProsConsEditor(props) {
		var a = props.attributes;
		var setAttributes = props.setAttributes;
		var blockProps = useBlockProps();
		var aiState = useState(''); // '', 'loading', 'error'
		var aiStatus = aiState[0];
		var setAiStatus = aiState[1];

		function generateWithAi() {
			if (!a.productId || !wp.apiFetch) return;
			setAiStatus('loading');
			wp.apiFetch({
				path: '/kalahamoon/v1/ai/generate-content',
				method: 'POST',
				data: {
					productId: a.productId,
					type: 'pros_cons',
					language: String(document.documentElement.lang || 'fa').toLowerCase().indexOf('en') === 0 ? 'en' : 'fa'
				},
			}).then(function (res) {
				var g = (res && res.generated) || res || {};
				var pros = g.pros || g.advantages || [];
				var cons = g.cons || g.disadvantages || [];
				var patch = {};
				if (Array.isArray(pros) && pros.length) {
					patch.pros = serializeLine(pros.map(function (p) { return serializeEntry('✅', String(p)); }));
				}
				if (Array.isArray(cons) && cons.length) {
					patch.cons = serializeLine(cons.map(function (c) { return serializeEntry('❌', String(c)); }));
				}
				setAttributes(patch);
				setAiStatus('');
			}).catch(function () {
				setAiStatus('error');
			});
		}

		function openPicker() {
			if (window.kalahamoonPicker) {
				window.kalahamoonPicker.open({
					multiple: false,
					initialIds: a.productId ? [a.productId] : [],
					title: __('Link to a product (optional)', 'kalahamoon'),
					onSelect: function (id) { setAttributes({ productId: id }); },
				});
			}
		}

		function addItem(field) {
			var arr = parseLine(a[field]);
			if (arr.length >= MAX_ITEMS) return;
			arr.push('');
			setAttributes((_a = {}, _a[field] = serializeLine(arr), _a));
			var _a;
		}

		function updateItemLabel(field, idx, val) {
			var arr = parseLine(a[field]);
			var entry = parseEntry(arr[idx] || '');
			arr[idx] = serializeEntry(entry.icon, val);
			setAttributes((_a = {}, _a[field] = serializeLine(arr), _a));
			var _a;
		}

		function updateItemIcon(field, idx, icon) {
			var arr = parseLine(a[field]);
			var entry = parseEntry(arr[idx] || '');
			arr[idx] = serializeEntry(icon, entry.label);
			setAttributes((_a = {}, _a[field] = serializeLine(arr), _a));
			var _a;
		}

		function removeItem(field, idx) {
			var arr = parseLine(a[field]);
			arr.splice(idx, 1);
			setAttributes((_a = {}, _a[field] = serializeLine(arr), _a));
			var _a;
		}

		var prosList = parseLine(a.pros);
		var consList = parseLine(a.cons);

		function renderItemRow(field, raw, idx, placeholder) {
			var entry = parseEntry(raw);
			return el('div', { key: field + '-' + idx, style: { display: 'flex', gap: '4px', marginBottom: '4px', alignItems: 'flex-start' } },
				el(SelectControl, {
					value: entry.icon || (field === 'pros' ? '✅' : '❌'),
					options: ICON_OPTIONS,
					onChange: function (v) { updateItemIcon(field, idx, v); },
					style: { minWidth: '58px', margin: 0 },
					hideLabelFromVision: true,
					label: __('Icon', 'kalahamoon'),
				}),
				el(TextControl, {
					value: entry.label,
					placeholder: placeholder,
					onChange: function (v) { updateItemLabel(field, idx, v); },
					style: { flex: 1, margin: 0 },
					hideLabelFromVision: true,
					label: __('Text', 'kalahamoon'),
				}),
				el(Button, {
					variant: 'tertiary',
					isDestructive: true,
					onClick: function () { removeItem(field, idx); },
					style: { padding: '0 4px', minWidth: 0 },
					label: __('Remove', 'kalahamoon'),
				}, '×')
			);
		}

		return el('div', blockProps,
			el(InspectorControls, null,
				el(PanelBody, { title: __('Column Labels', 'kalahamoon'), initialOpen: true },
					el(TextControl, {
						label: __('Pros column label', 'kalahamoon'),
						value: a.prosLabel !== undefined ? a.prosLabel : __('نقاط مثبت', 'kalahamoon'),
						onChange: function (v) { setAttributes({ prosLabel: v }); },
					}),
					el(TextControl, {
						label: __('Cons column label', 'kalahamoon'),
						value: a.consLabel !== undefined ? a.consLabel : __('نقاط منفی', 'kalahamoon'),
						onChange: function (v) { setAttributes({ consLabel: v }); },
					})
				),
				el(PanelBody, { title: __('Product (optional)', 'kalahamoon'), initialOpen: false },
					el('div', { style: { marginBottom: '12px' } },
						el(Button, { variant: 'secondary', onClick: openPicker, style: { width: '100%', justifyContent: 'center' } },
							a.productId ? __('Change product…', 'kalahamoon') : __('Link a product…', 'kalahamoon')
						)
					),
					a.productId
						? el('div', { style: { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px' } },
							el('code', { style: { fontSize: '11px', background: '#f3f4f6', padding: '2px 6px', borderRadius: '4px', flex: 1 } }, a.productId),
							el(Button, { variant: 'tertiary', isDestructive: true, onClick: function () { setAttributes({ productId: '' }); } }, '×')
						)
						: null,
					el(ToggleControl, {
						label: __('Show CTA Button', 'kalahamoon'),
						checked: a.showCta,
						onChange: function (v) { setAttributes({ showCta: v }); },
					}),
					a.showCta
						? el(TextControl, {
							label: __('CTA Text', 'kalahamoon'),
							value: a.ctaText,
							onChange: function (v) { setAttributes({ ctaText: v }); },
						})
						: null,
					a.productId
						? el('div', { style: { marginTop: '12px' } },
							el(Button, {
								variant: 'secondary',
								onClick: generateWithAi,
								disabled: aiStatus === 'loading',
								style: { width: '100%', justifyContent: 'center' },
							}, aiStatus === 'loading' ? __('Generating…', 'kalahamoon') : __('Generate pros & cons with AI', 'kalahamoon')),
							aiStatus === 'error'
								? el('p', { style: { color: '#dc2626', fontSize: '11px', margin: '6px 0 0' } }, __('Could not generate. Check your Kalahamoon connection.', 'kalahamoon'))
								: null
						)
						: null
				)
			),
			// Inline editor UI
			el('div', { className: 'kalahamoon-pros-cons-editor' },
				el(TextControl, {
					label: __('Card heading (optional)', 'kalahamoon'),
					value: a.heading,
					placeholder: __('e.g. LG Washing Machine — Our Verdict', 'kalahamoon'),
					onChange: function (v) { setAttributes({ heading: v }); },
				}),
				el('div', { className: 'kalahamoon-pros-cons-cols' },
					// Pros column
					el('div', { className: 'kalahamoon-pros-cons-col kalahamoon-pros-col' },
						el('strong', { className: 'kalahamoon-pc-col-label kalahamoon-pc-pros-label' }, '✅ ' + (a.prosLabel || __('نقاط مثبت', 'kalahamoon'))),
						prosList.map(function (raw, idx) {
							return renderItemRow('pros', raw, idx, __('Add a pro…', 'kalahamoon'));
						}),
						prosList.length < MAX_ITEMS
							? el(Button, { variant: 'tertiary', onClick: function () { addItem('pros'); }, icon: 'plus-alt2' }, __('Add pro', 'kalahamoon'))
							: el('p', { style: { fontSize: '11px', color: '#94a3b8', margin: '4px 0 0' } }, __('Maximum 10 items reached.', 'kalahamoon'))
					),
					// Cons column
					el('div', { className: 'kalahamoon-pros-cons-col kalahamoon-cons-col' },
						el('strong', { className: 'kalahamoon-pc-col-label kalahamoon-pc-cons-label' }, '❌ ' + (a.consLabel || __('نقاط منفی', 'kalahamoon'))),
						consList.map(function (raw, idx) {
							return renderItemRow('cons', raw, idx, __('Add a con…', 'kalahamoon'));
						}),
						consList.length < MAX_ITEMS
							? el(Button, { variant: 'tertiary', onClick: function () { addItem('cons'); }, icon: 'plus-alt2' }, __('Add con', 'kalahamoon'))
							: el('p', { style: { fontSize: '11px', color: '#94a3b8', margin: '4px 0 0' } }, __('Maximum 10 items reached.', 'kalahamoon'))
					)
				)
			)
		);
	}

	registerBlockType('kalahamoon/pros-cons', {
		edit: ProsConsEditor,
	});
})(window.wp);
