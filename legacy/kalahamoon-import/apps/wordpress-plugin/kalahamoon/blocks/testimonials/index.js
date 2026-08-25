/**
 * Kalahamoon Testimonials — Gutenberg block editor script.
 */
(function (wp) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var ToggleControl = wp.components.ToggleControl;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var Button = wp.components.Button;
	var MediaUpload = wp.blockEditor.MediaUpload;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	function setItem(items, i, patch) {
		return items.map(function (x, idx) { return idx === i ? Object.assign({}, x, patch) : x; });
	}

	registerBlockType('kalahamoon/testimonials', {
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var items = attributes.items || [];
			var blockProps = useBlockProps({ className: 'kalahamoon-testimonials-editor' });

			function add() {
				setAttributes({ items: items.concat([{ quote: '', author: '', role: '', avatar: '', rating: 5 }]) });
			}
			function remove(i) {
				setAttributes({ items: items.filter(function (_, idx) { return idx !== i; }) });
			}

			return el('div', blockProps,
				el(InspectorControls, null,
					el(PanelBody, { title: __('Layout', 'kalahamoon'), initialOpen: true },
						el(SelectControl, {
							label: __('Layout', 'kalahamoon'),
							value: attributes.layout,
							options: [
								{ label: __('Grid',   'kalahamoon'), value: 'grid' },
								{ label: __('Slider', 'kalahamoon'), value: 'slider' },
							],
							onChange: function (v) { setAttributes({ layout: v }); },
						}),
						attributes.layout === 'grid' && el(RangeControl, {
							label: __('Columns', 'kalahamoon'),
							value: attributes.columns || 2,
							onChange: function (v) { setAttributes({ columns: v }); },
							min: 1, max: 4,
						}),
						el(ToggleControl, {
							label: __('Show star rating', 'kalahamoon'),
							checked: attributes.showRating !== false,
							onChange: function (v) { setAttributes({ showRating: v }); },
						})
					),
					el(PanelBody, { title: __('Items', 'kalahamoon'), initialOpen: true },
						items.map(function (item, i) {
							return el('div', { key: i, className: 'kalahamoon-testimonials-edit-row' },
								el(TextareaControl, { label: __('Quote', 'kalahamoon'),  value: item.quote,  onChange: function (v) { setAttributes({ items: setItem(items, i, { quote: v }) }); }, rows: 3 }),
								el(TextControl,    { label: __('Author', 'kalahamoon'), value: item.author, onChange: function (v) { setAttributes({ items: setItem(items, i, { author: v }) }); } }),
								el(TextControl,    { label: __('Role / company', 'kalahamoon'), value: item.role, onChange: function (v) { setAttributes({ items: setItem(items, i, { role: v }) }); } }),
								el(RangeControl,   { label: __('Rating', 'kalahamoon'), value: item.rating || 5, onChange: function (v) { setAttributes({ items: setItem(items, i, { rating: v }) }); }, min: 1, max: 5 }),
								el(MediaUpload, {
									onSelect: function (m) { setAttributes({ items: setItem(items, i, { avatar: m && m.url ? m.url : '' }) }); },
									allowedTypes: ['image'],
									value: item.avatar,
									render: function (obj) { return el(Button, { variant: 'secondary', onClick: obj.open }, item.avatar ? __('Change avatar', 'kalahamoon') : __('Add avatar', 'kalahamoon')); },
								}),
								el(Button, { variant: 'tertiary', isDestructive: true, onClick: function () { remove(i); } }, __('Remove', 'kalahamoon'))
							);
						}),
						el(Button, { variant: 'primary', onClick: add }, __('Add testimonial', 'kalahamoon'))
					)
				),
				(items.length && ServerSideRender)
					? el(ServerSideRender, { block: 'kalahamoon/testimonials', attributes: attributes })
					: el('div', { className: 'kalahamoon-block-placeholder' },
						el('p', { style: { marginBottom: '12px' } }, __('Add testimonials from the block sidebar to preview them here.', 'kalahamoon')),
						el(Button, { variant: 'primary', onClick: add }, __('Add testimonial', 'kalahamoon'))
					)
			);
		},
	});
})(window.wp);
