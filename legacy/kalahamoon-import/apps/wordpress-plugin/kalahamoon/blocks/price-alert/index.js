(function (wp) {
	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	registerBlockType('kalahamoon/price-alert', {
		edit: function (props) {
			var a = props.attributes;
			var set = props.setAttributes;

			function openPicker() {
				if (window.kalahamoonPicker) {
					window.kalahamoonPicker.open({
						multiple: false,
						initialIds: a.productId ? [a.productId] : [],
						title: __('Select a product', 'kalahamoon'),
						onSelect: function (id) { set({ productId: id }); },
					});
				}
			}

			return el('div', useBlockProps(),
				el(InspectorControls, null,
					el(PanelBody, { title: __('Price alert', 'kalahamoon'), initialOpen: true },
						el('div', { style: { marginBottom: '12px' } },
							el(Button, { variant: 'secondary', onClick: openPicker, style: { width: '100%', justifyContent: 'center' } },
								a.productId ? __('Change product…', 'kalahamoon') : __('Select product…', 'kalahamoon')
							)
						),
						el(TextControl, { label: __('Heading', 'kalahamoon'), value: a.heading || '', onChange: function (v) { set({ heading: v }); } }),
						el(TextControl, { label: __('Button text', 'kalahamoon'), value: a.buttonText || '', onChange: function (v) { set({ buttonText: v }); } }),
						el(TextControl, { label: __('Success message', 'kalahamoon'), value: a.successText || '', onChange: function (v) { set({ successText: v }); } })
					)
				),
				a.productId
					? el(ServerSideRender, { block: 'kalahamoon/price-alert', attributes: a })
					: el('div', { className: 'kalahamoon-block-placeholder' },
						el('p', { style: { marginBottom: '12px' } }, __('Select a product for the price alert.', 'kalahamoon')),
						el(Button, { variant: 'primary', onClick: openPicker }, __('Select product…', 'kalahamoon'))
					)
			);
		}
	});
})(window.wp);
