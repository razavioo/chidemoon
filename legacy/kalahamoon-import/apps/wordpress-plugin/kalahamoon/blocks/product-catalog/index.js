(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	registerBlockType('kalahamoon/product-catalog', {
		edit: function (props) {
			var a = props.attributes;
			var set = props.setAttributes;

			return el('div', useBlockProps(),
				el(InspectorControls, null,
					el(PanelBody, { title: __('Catalog settings', 'kalahamoon'), initialOpen: true },
						el(TextControl, { label: __('Heading', 'kalahamoon'), value: a.heading || '', onChange: function (value) { set({ heading: value }); } }),
						el(TextControl, { label: __('Description', 'kalahamoon'), value: a.description || '', onChange: function (value) { set({ description: value }); } }),
						el(RangeControl, { label: __('Products per page', 'kalahamoon'), value: a.perPage || 12, min: 4, max: 24, onChange: function (value) { set({ perPage: value }); } }),
						el(RangeControl, { label: __('Columns', 'kalahamoon'), value: a.columns || 3, min: 2, max: 4, onChange: function (value) { set({ columns: value }); } }),
						el(ToggleControl, { label: __('Show filters', 'kalahamoon'), checked: a.showFilters !== false, onChange: function (value) { set({ showFilters: value }); } }),
						el(ToggleControl, { label: __('Show quick view', 'kalahamoon'), checked: a.showQuickView !== false, onChange: function (value) { set({ showQuickView: value }); } }),
						el(ToggleControl, { label: __('Show favorites', 'kalahamoon'), checked: a.showFavorites !== false, onChange: function (value) { set({ showFavorites: value }); } }),
						el(ToggleControl, { label: __('Show comparison', 'kalahamoon'), checked: a.showCompare !== false, onChange: function (value) { set({ showCompare: value }); } })
					)
				),
				el(ServerSideRender, { block: 'kalahamoon/product-catalog', attributes: a })
			);
		}
	});
})(window.wp);
