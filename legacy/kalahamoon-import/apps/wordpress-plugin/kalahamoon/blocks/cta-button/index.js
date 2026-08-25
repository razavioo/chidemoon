(function (wp) {
	var el = wp.element.createElement;
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

	registerBlockType('kalahamoon/cta-button', {
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
					el(PanelBody, { title: __('Target product', 'kalahamoon'), initialOpen: true },
						el('div', { style: { marginBottom: '12px' } },
							el(Button, { variant: 'secondary', onClick: openPicker, style: { width: '100%', justifyContent: 'center' } },
								a.productId ? __('Change product…', 'kalahamoon') : __('Select product…', 'kalahamoon')
							)
						),
						a.productId
							? el(TextControl, {
								label: __('Product ID', 'kalahamoon'),
								value: a.productId,
								onChange: function (v) { setAttributes({ productId: v }); },
							})
							: null,
						el(TextControl, { label: __('Button Text', 'kalahamoon'), value: a.text, onChange: function (v) { setAttributes({ text: v }); } }),
						el(ToggleControl, { label: __('Show Price', 'kalahamoon'), checked: a.showPrice, onChange: function (v) { setAttributes({ showPrice: v }); } }),
						el(TextControl, {
						label: __('Custom product URL', 'kalahamoon'),
						help: __('Leave empty to use the product link that is available.', 'kalahamoon'),
							value: a.customUrl || '',
							onChange: function (v) { setAttributes({ customUrl: v }); },
							type: 'url',
						})
					),
					el(PanelBody, { title: __('Appearance', 'kalahamoon'), initialOpen: false },
						el(SelectControl, {
							label: __('Size', 'kalahamoon'),
							value: a.size,
							options: [
								{ label: __('Small', 'kalahamoon'),  value: 'small' },
								{ label: __('Medium', 'kalahamoon'), value: 'medium' },
								{ label: __('Large', 'kalahamoon'),  value: 'large' },
							],
							onChange: function (v) { setAttributes({ size: v }); },
						}),
						el(SelectControl, {
							label: __('Icon', 'kalahamoon'),
							value: a.icon || 'none',
							options: [
								{ label: __('No icon', 'kalahamoon'), value: 'none' },
								{ label: __('Cart', 'kalahamoon'),    value: 'cart' },
								{ label: __('Bolt', 'kalahamoon'),    value: 'bolt' },
								{ label: __('Tag',  'kalahamoon'),    value: 'tag' },
								{ label: __('Arrow','kalahamoon'),    value: 'arrow' },
								{ label: __('Heart','kalahamoon'),    value: 'heart' },
							],
							onChange: function (v) { setAttributes({ icon: v }); },
						}),
						(a.icon && a.icon !== 'none')
							? el(SelectControl, {
								label: __('Icon position', 'kalahamoon'),
								value: a.iconPosition || 'start',
								options: [
									{ label: __('At start (before text)', 'kalahamoon'), value: 'start' },
									{ label: __('At end (after text)', 'kalahamoon'),    value: 'end' },
								],
								onChange: function (v) { setAttributes({ iconPosition: v }); },
							})
							: null,
						el(ToggleControl, {
							label: __('Full width', 'kalahamoon'),
							checked: !!a.fullWidth,
							onChange: function (v) { setAttributes({ fullWidth: v }); },
							help: __('Makes the button stretch across its container.', 'kalahamoon'),
						})
					)
				),
				a.productId
					? el(ServerSideRender, { block: 'kalahamoon/cta-button', attributes: a })
					: el('div', { className: 'kalahamoon-block-placeholder' },
						el('p', { style: { marginBottom: '12px' } }, __('Select a product for this button.', 'kalahamoon')),
						el(Button, { variant: 'primary', onClick: openPicker }, __('Select product…', 'kalahamoon'))
					)
			);
		}
	});
})(window.wp);
