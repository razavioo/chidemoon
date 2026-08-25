/**
 * Kalahamoon Product Box — Gutenberg block editor script.
 */
(function (wp) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var SelectControl = wp.components.SelectControl;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	registerBlockType('kalahamoon/product-box', {
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			function openPicker() {
				if (window.kalahamoonPicker) {
					window.kalahamoonPicker.open({
						multiple: false,
						initialIds: attributes.productId ? [attributes.productId] : [],
						title: __('Select a product', 'kalahamoon'),
						onSelect: function (id) { setAttributes({ productId: id }); },
					});
				}
			}

			return el('div', blockProps,
				el(InspectorControls, null,
					el(PanelBody, { title: __('Product', 'kalahamoon'), initialOpen: true },
						el('div', { style: { marginBottom: '12px' } },
							el(Button, {
								variant: 'secondary',
								onClick: openPicker,
								style: { width: '100%', justifyContent: 'center' },
							},
								attributes.productId
									? __('Change product…', 'kalahamoon')
									: __('Select product…', 'kalahamoon')
							)
						),
						attributes.productId
							? el(TextControl, {
								label: __('Product ID', 'kalahamoon'),
								value: attributes.productId,
								onChange: function (val) { setAttributes({ productId: val }); },
								help: __('Or type an ID directly', 'kalahamoon'),
							})
							: null,
						el(SelectControl, {
							label: __('Badge / Variant', 'kalahamoon'),
							value: attributes.variant || '',
							options: [
								{ label: __('None', 'kalahamoon'),          value: '' },
								{ label: __('Bestseller', 'kalahamoon'),    value: 'bestseller' },
								{ label: __('On Sale', 'kalahamoon'),       value: 'on-sale' },
								{ label: __('New Arrival', 'kalahamoon'),   value: 'new-arrival' },
							],
							onChange: function (val) { setAttributes({ variant: val }); },
						}),
						el(TextControl, {
							label: __('CTA Text', 'kalahamoon'),
							value: attributes.ctaText,
							onChange: function (val) { setAttributes({ ctaText: val }); },
						})
					),
					el(PanelBody, { title: __('Layout & Shape', 'kalahamoon'), initialOpen: false },
						el(SelectControl, {
							label: __('Orientation', 'kalahamoon'),
							value: attributes.layout,
							options: [
								{ label: __('Vertical (image top)', 'kalahamoon'),   value: 'vertical' },
								{ label: __('Horizontal (image start)', 'kalahamoon'), value: 'horizontal' },
							],
							onChange: function (val) { setAttributes({ layout: val }); },
						}),
						el(SelectControl, {
							label: __('Image Aspect Ratio', 'kalahamoon'),
							value: attributes.imageAspectRatio || '1/1',
							options: [
								{ label: __('Square (1:1)', 'kalahamoon'),       value: '1/1' },
								{ label: __('Landscape (4:3)', 'kalahamoon'),    value: '4/3' },
								{ label: __('Portrait (3:4)', 'kalahamoon'),     value: '3/4' },
								{ label: __('Wide (16:9)', 'kalahamoon'),        value: '16/9' },
								{ label: __('Natural (unconstrained)', 'kalahamoon'), value: 'auto' },
							],
							onChange: function (val) { setAttributes({ imageAspectRatio: val }); },
						}),
						el(SelectControl, {
							label: __('Hover Effect', 'kalahamoon'),
							value: attributes.hoverEffect || 'lift',
							options: [
								{ label: __('None', 'kalahamoon'),    value: 'none' },
								{ label: __('Lift',  'kalahamoon'),   value: 'lift' },
								{ label: __('Zoom image', 'kalahamoon'), value: 'zoom' },
								{ label: __('Glow border', 'kalahamoon'), value: 'glow' },
							],
							onChange: function (val) { setAttributes({ hoverEffect: val }); },
							help: __('Visual feedback when the card is hovered or focused.', 'kalahamoon'),
						}),
						el(SelectControl, {
							label: __('Title heading level', 'kalahamoon'),
							value: String(attributes.headingLevel || 3),
							options: [
								{ label: 'H2', value: '2' },
								{ label: 'H3', value: '3' },
								{ label: 'H4', value: '4' },
								{ label: 'H5', value: '5' },
								{ label: 'H6', value: '6' },
							],
							onChange: function (val) { setAttributes({ headingLevel: parseInt(val, 10) || 3 }); },
							help: __('Use a heading level that fits your page outline for SEO and a11y.', 'kalahamoon'),
						})
					),
					el(PanelBody, { title: __('Visibility', 'kalahamoon'), initialOpen: false },
						el(ToggleControl, {
							label: __('Show Title', 'kalahamoon'),
							checked: attributes.showTitle !== false,
							onChange: function (val) { setAttributes({ showTitle: val }); },
						}),
						el(ToggleControl, {
							label: __('Show Price', 'kalahamoon'),
							checked: attributes.showPrice !== false,
							onChange: function (val) { setAttributes({ showPrice: val }); },
						}),
						el(ToggleControl, {
							label: __('Show Old Price / Discount', 'kalahamoon'),
							checked: attributes.showOldPrice !== false,
							onChange: function (val) { setAttributes({ showOldPrice: val }); },
						}),
						el(ToggleControl, {
							label: __('Show Marketplace Badge', 'kalahamoon'),
							checked: attributes.showMarketplaceBadge !== false,
							onChange: function (val) { setAttributes({ showMarketplaceBadge: val }); },
						}),
						el(ToggleControl, {
							label: __('Show CTA Button', 'kalahamoon'),
							checked: attributes.showCta !== false,
							onChange: function (val) { setAttributes({ showCta: val }); },
						})
					)
				),
				attributes.productId
					? el(ServerSideRender, { block: 'kalahamoon/product-box', attributes: attributes })
					: el('div', { className: 'kalahamoon-block-placeholder' },
						el('p', { style: { marginBottom: '12px' } }, __('Choose a product to display.', 'kalahamoon')),
						el(Button, { variant: 'primary', onClick: openPicker }, __('Select product…', 'kalahamoon'))
					)
			);
		},
	});
})(window.wp);
