(function (wp) {
	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var RangeControl = wp.components.RangeControl;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	registerBlockType('kalahamoon/price-comparison', {
		edit: function (props) {
			var a = props.attributes;
			var setAttributes = props.setAttributes;

			function openPicker() {
				if (window.kalahamoonPicker) {
					window.kalahamoonPicker.open({
						multiple: false,
						initialIds: a.productId ? [a.productId] : [],
						title: __('Select a product', 'kalahamoon'),
						onSelect: function (id) { setAttributes({ productId: id }); },
					});
				}
			}

			return el('div', useBlockProps(),
				el(InspectorControls, null,
					el(PanelBody, { title: __('Product', 'kalahamoon'), initialOpen: true },
						el('div', { style: { marginBottom: '12px' } },
							el(Button, { variant: 'secondary', onClick: openPicker, style: { width: '100%', justifyContent: 'center' } },
								a.productId ? __('Change product…', 'kalahamoon') : __('Select product…', 'kalahamoon')
							)
						),
						el(TextControl, { label: __('Heading', 'kalahamoon'), value: a.heading || '', onChange: function (v) { setAttributes({ heading: v }); } }),
						el(ToggleControl, { label: __('Show stock', 'kalahamoon'), checked: !!a.showStock, onChange: function (v) { setAttributes({ showStock: v }); } }),
						el(RangeControl, { label: __('Max rows', 'kalahamoon'), min: 1, max: 20, value: a.maxRows || 8, onChange: function (v) { setAttributes({ maxRows: v }); } })
					)
				),
				a.productId
					? el(ServerSideRender, { block: 'kalahamoon/price-comparison', attributes: a })
					: el('div', { className: 'kalahamoon-block-placeholder' },
						el('p', { style: { marginBottom: '12px' } }, __('Select a product to compare marketplace prices.', 'kalahamoon')),
						el(Button, { variant: 'primary', onClick: openPicker }, __('Select product…', 'kalahamoon'))
					)
			);
		}
	});
})(window.wp);
