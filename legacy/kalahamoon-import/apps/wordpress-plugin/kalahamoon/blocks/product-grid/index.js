(function (wp) {
	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var registerBlockVariation = wp.blocks.registerBlockVariation;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var BlockControls = wp.blockEditor.BlockControls;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;
	var RangeControl = wp.components.RangeControl;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var ToolbarGroup = wp.components.ToolbarGroup;
	var ToolbarButton = wp.components.ToolbarButton;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	var LAYOUT_OPTIONS = [
		{ value: 'grid',     label: __('Grid', 'kalahamoon'),     icon: 'grid-view' },
		{ value: 'masonry',  label: __('Masonry', 'kalahamoon'),  icon: 'layout' },
		{ value: 'list',     label: __('List', 'kalahamoon'),     icon: 'list-view' },
		{ value: 'carousel', label: __('Carousel', 'kalahamoon'), icon: 'slides' },
	];

	registerBlockType('kalahamoon/product-grid', {
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
						title: __('Select products for grid', 'kalahamoon'),
						onSelect: function (ids) { setAttributes({ productIds: ids.join(','), category: '' }); },
					});
				}
			}

			var layoutToolbar = el(BlockControls, null,
				el(ToolbarGroup, null,
					LAYOUT_OPTIONS.map(function (opt) {
						return el(ToolbarButton, {
							key: opt.value,
							icon: opt.icon,
							label: opt.label,
							isActive: (a.layout || 'grid') === opt.value,
							onClick: function () { setAttributes({ layout: opt.value }); },
						});
					})
				)
			);

			return el('div', useBlockProps({ 'data-layout': a.layout || 'grid' }),
				layoutToolbar,
				el(InspectorControls, null,
					el(PanelBody, { title: __('Grid Settings', 'kalahamoon'), initialOpen: true },
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
								help: __('Or type IDs directly', 'kalahamoon'),
							})
							: null,
						el(TextControl, {
							label: __('Category (instead of IDs)', 'kalahamoon'),
							value: a.category,
							onChange: function (v) { setAttributes({ category: v, productIds: '' }); },
						}),
						el(SelectControl, {
							label: __('Layout', 'kalahamoon'),
							value: a.layout || 'grid',
							options: LAYOUT_OPTIONS.map(function (o) { return { value: o.value, label: o.label }; }),
							onChange: function (v) { setAttributes({ layout: v }); },
							help: __('Grid is the default; carousel scrolls horizontally.', 'kalahamoon'),
						}),
						el(SelectControl, {
							label: __('Order by', 'kalahamoon'),
							value: a.orderBy || 'newest',
							options: [
								{ label: __('Newest first', 'kalahamoon'),      value: 'newest' },
								{ label: __('Oldest first', 'kalahamoon'),      value: 'oldest' },
								{ label: __('Price: low → high', 'kalahamoon'), value: 'price_asc' },
								{ label: __('Price: high → low', 'kalahamoon'), value: 'price_desc' },
								{ label: __('Title (A→Z)', 'kalahamoon'),       value: 'title' },
								{ label: __('Random', 'kalahamoon'),            value: 'random' },
							],
							onChange: function (v) { setAttributes({ orderBy: v }); },
							help: a.ranked ? __('Ignored while ranking is on.', 'kalahamoon') : '',
						}),
						el(RangeControl, { label: __('Columns', 'kalahamoon'), value: a.columns, min: 2, max: 4, onChange: function (v) { setAttributes({ columns: v }); } }),
						el(RangeControl, { label: __('Limit', 'kalahamoon'), value: a.limit, min: 1, max: 12, onChange: function (v) { setAttributes({ limit: v }); } }),
						el(ToggleControl, { label: __('Show Ranking (🥇🥈🥉)', 'kalahamoon'), checked: a.ranked, onChange: function (v) { setAttributes({ ranked: v }); } })
					)
				),
				(a.productIds || a.category)
					? el(ServerSideRender, { block: 'kalahamoon/product-grid', attributes: a })
					: el('div', { style: { padding: '40px', textAlign: 'center', background: 'var(--wp-admin-theme-color-alt, #f0f0f1)', border: '2px dashed #c3c4c7', borderRadius: '12px', color: '#50575e' } },
						el('p', { style: { marginBottom: '12px' } }, __('Select products or enter a category.', 'kalahamoon')),
						el(Button, { variant: 'primary', onClick: openPicker }, __('Select products…', 'kalahamoon'))
					)
			);
		}
	});

	// ─── Block variations — surface layouts as distinct inserter items ──
	[
		{ name: 'masonry',  title: __('Product Masonry', 'kalahamoon'),  icon: 'layout',
		  description: __('Multi-column masonry grid that flows card heights naturally.', 'kalahamoon'),
		  attributes: { layout: 'masonry', columns: 3 } },
		{ name: 'list',     title: __('Product List', 'kalahamoon'),     icon: 'list-view',
		  description: __('Single-column list with horizontal product cards.', 'kalahamoon'),
		  attributes: { layout: 'list', columns: 1 } },
		{ name: 'carousel', title: __('Product Carousel', 'kalahamoon'), icon: 'slides',
		  description: __('Horizontal scroll-snap carousel — best for 6+ products.', 'kalahamoon'),
		  attributes: { layout: 'carousel' } },
	].forEach(function (v) {
		registerBlockVariation('kalahamoon/product-grid', Object.assign({ scope: ['inserter', 'transform'] }, v));
	});
})(window.wp);
