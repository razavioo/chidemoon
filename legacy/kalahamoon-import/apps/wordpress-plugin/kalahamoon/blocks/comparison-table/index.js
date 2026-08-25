(function (wp) {
	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	registerBlockType('kalahamoon/comparison-table', {
		edit: function (props) {
			var a = props.attributes;
			var setAttributes = props.setAttributes;

			function openPicker() {
				if (window.kalahamoonPicker) {
					var initial = a.productIds
						? a.productIds.split(',').map(function (s) { return s.trim(); }).filter(Boolean)
						: [];
					window.kalahamoonPicker.open({
						multiple: true,
						initialIds: initial,
						title: __('Select products to compare (2–5)', 'kalahamoon'),
						onSelect: function (ids) { setAttributes({ productIds: ids.join(',') }); },
					});
				}
			}

			return el('div', useBlockProps(),
				el(InspectorControls, null,
					el(PanelBody, { title: __('Comparison Settings', 'kalahamoon'), initialOpen: true },
						el('div', { style: { marginBottom: '12px' } },
							el(Button, { variant: 'secondary', onClick: openPicker, style: { width: '100%', justifyContent: 'center' } },
								a.productIds
									? __('Change products…', 'kalahamoon')
									: __('Select products…', 'kalahamoon')
							)
						),
						a.productIds
							? el(TextControl, {
								label: __('Product IDs', 'kalahamoon'),
								value: a.productIds,
								onChange: function (v) { setAttributes({ productIds: v }); },
								help: __('Comma-separated, 2–5 products', 'kalahamoon'),
							})
							: null,
						el(TextControl, {
							label: __('Spec Keys (comma-separated)', 'kalahamoon'),
							value: a.specs,
							onChange: function (v) { setAttributes({ specs: v }); },
							help: __('Leave empty to show all specs', 'kalahamoon'),
						})
					)
				),
				a.productIds
					? el(ServerSideRender, { block: 'kalahamoon/comparison-table', attributes: a })
					: el('div', { style: { padding: '40px', textAlign: 'center', background: '#f9fafb', border: '2px dashed #e5e7eb', borderRadius: '12px', color: '#6b7280' } },
						el('p', { style: { marginBottom: '12px' } }, __('Select 2–5 products to compare.', 'kalahamoon')),
						el(Button, { variant: 'primary', onClick: openPicker }, __('Select products…', 'kalahamoon'))
					)
			);
		}
	});
})(window.wp);
