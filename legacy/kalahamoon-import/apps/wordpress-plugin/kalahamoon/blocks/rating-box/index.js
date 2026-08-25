(function (wp) {
	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var ToggleControl = wp.components.ToggleControl;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	function RatingBoxEditor(props) {
		var a = props.attributes;
		var setAttributes = props.setAttributes;
		var blockProps = useBlockProps();

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

		var scoreMax = a.scoreMax || 10;

		return el('div', blockProps,
			el(InspectorControls, null,
				el(PanelBody, { title: __('Score', 'kalahamoon'), initialOpen: true },
					el(SelectControl, {
						label: __('Scale', 'kalahamoon'),
						value: String(scoreMax),
						options: [
							{ label: __('Out of 5', 'kalahamoon'),  value: '5' },
							{ label: __('Out of 10', 'kalahamoon'), value: '10' },
						],
						onChange: function (v) { setAttributes({ scoreMax: parseInt(v, 10) }); },
					}),
					el(RangeControl, {
						label: __('Overall score', 'kalahamoon'),
						value: a.score,
						min: 0,
						max: scoreMax,
						step: 0.5,
						onChange: function (v) { setAttributes({ score: v }); },
					}),
					el(TextControl, {
						label: __('Score label (e.g. "عالی")', 'kalahamoon'),
						help: __('Leave blank to use automatic label.', 'kalahamoon'),
						value: a.scoreLabel || '',
						onChange: function (v) { setAttributes({ scoreLabel: v }); },
					}),
					el(ToggleControl, {
						label: __('Show stars', 'kalahamoon'),
						checked: !!a.showStars,
						onChange: function (v) { setAttributes({ showStars: v }); },
					})
				),
				el(PanelBody, { title: __('Criteria', 'kalahamoon'), initialOpen: false },
					el(TextareaControl, {
						label: __('Criteria (one per line: "Label:Score")', 'kalahamoon'),
						help: __('Example:\nقیمت:8.5\nکیفیت:9\nطراحی:7', 'kalahamoon'),
						value: a.criteria || '',
						rows: 6,
						onChange: function (v) { setAttributes({ criteria: v }); },
					})
				),
				el(PanelBody, { title: __('Content', 'kalahamoon'), initialOpen: false },
					el(TextControl, {
						label: __('Block heading', 'kalahamoon'),
						value: a.heading || '',
						onChange: function (v) { setAttributes({ heading: v }); },
					}),
					el(TextareaControl, {
						label: __('Verdict / summary', 'kalahamoon'),
						value: a.verdict || '',
						rows: 3,
						onChange: function (v) { setAttributes({ verdict: v }); },
					})
				),
				el(PanelBody, { title: __('Product & CTA', 'kalahamoon'), initialOpen: false },
					el('div', { style: { marginBottom: '12px' } },
						el(Button, {
							variant: 'secondary',
							onClick: openPicker,
							style: { width: '100%', justifyContent: 'center' },
						}, a.productId ? __('Change product…', 'kalahamoon') : __('Link a product…', 'kalahamoon'))
					),
					a.productId
						? el('div', { style: { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px' } },
							el('code', { style: { fontSize: '11px', background: '#f3f4f6', padding: '2px 6px', borderRadius: '4px', flex: 1 } }, a.productId),
							el(Button, { variant: 'tertiary', isDestructive: true, onClick: function () { setAttributes({ productId: '', productName: '' }); } }, '×')
						)
						: el(TextControl, {
							label: __('Product name (for schema, if no product linked)', 'kalahamoon'),
							value: a.productName || '',
							onChange: function (v) { setAttributes({ productName: v }); },
						}),
					el(ToggleControl, {
						label: __('Show CTA button', 'kalahamoon'),
						checked: !!a.showCta,
						onChange: function (v) { setAttributes({ showCta: v }); },
					}),
					a.showCta
						? el(TextControl, {
							label: __('CTA text', 'kalahamoon'),
							value: a.ctaText || '',
							onChange: function (v) { setAttributes({ ctaText: v }); },
						})
						: null
				),
				el(PanelBody, { title: __('SEO', 'kalahamoon'), initialOpen: false },
					el(ToggleControl, {
						label: __('Output Schema.org Review JSON-LD', 'kalahamoon'),
						checked: !!a.showSchema,
						onChange: function (v) { setAttributes({ showSchema: v }); },
					})
				)
			),
			el(ServerSideRender, { block: 'kalahamoon/rating-box', attributes: a })
		);
	}

	registerBlockType('kalahamoon/rating-box', {
		edit: RatingBoxEditor,
	});
})(window.wp);
