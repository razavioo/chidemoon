(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	registerBlockType('kalahamoon/product-comparison', {
		edit: function (props) {
			var attributes = props.attributes;
			return el('div', useBlockProps(),
				el(InspectorControls, null,
					el(PanelBody, { title: __('Comparison settings', 'kalahamoon'), initialOpen: true },
						el(TextControl, {
							label: __('Product IDs', 'kalahamoon'),
							help: __('Enter two to four comma-separated IDs. Leave empty to read the public comparison URL.', 'kalahamoon'),
							value: attributes.productIds || '',
							onChange: function (value) { props.setAttributes({ productIds: value }); }
						}),
						el(TextControl, {
							label: __('Heading', 'kalahamoon'),
							value: attributes.heading || '',
							onChange: function (value) { props.setAttributes({ heading: value }); }
						}),
						el(ToggleControl, {
							label: __('Show affiliate disclosure', 'kalahamoon'),
							checked: attributes.showDisclosure !== false,
							onChange: function (value) { props.setAttributes({ showDisclosure: value }); }
						})
					)
				),
				el(ServerSideRender, { block: 'kalahamoon/product-comparison', attributes: attributes })
			);
		}
	});
})(window.wp);
