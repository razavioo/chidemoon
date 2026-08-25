/**
 * Kalahamoon FAQ — Gutenberg block editor script.
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
	var Button = wp.components.Button;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var __ = wp.i18n.__;

	function updateItem(items, index, patch) {
		return items.map(function (it, i) { return i === index ? Object.assign({}, it, patch) : it; });
	}

	registerBlockType('kalahamoon/faq', {
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps({ className: 'kalahamoon-faq-editor' });
			var items = attributes.items || [];
			var headingTag = 'h' + Math.max(2, Math.min(6, parseInt(attributes.headingLevel, 10) || 3));

			function addItem() {
				setAttributes({ items: items.concat([{ q: __('سوال جدید', 'kalahamoon'), a: __('پاسخ…', 'kalahamoon') }]) });
			}
			function removeItem(i) {
				setAttributes({ items: items.filter(function (_, idx) { return idx !== i; }) });
			}

			return el('div', blockProps,
				el(InspectorControls, null,
					el(PanelBody, { title: __('Behavior', 'kalahamoon'), initialOpen: true },
						el(ToggleControl, {
							label: __('Open first item by default', 'kalahamoon'),
							checked: !!attributes.openFirst,
							onChange: function (v) { setAttributes({ openFirst: v }); },
						}),
						el(ToggleControl, {
							label: __('Emit FAQPage Schema.org JSON-LD', 'kalahamoon'),
							help: __('Recommended for SEO rich results.', 'kalahamoon'),
							checked: attributes.emitSchema !== false,
							onChange: function (v) { setAttributes({ emitSchema: v }); },
						}),
						el(SelectControl, {
							label: __('Question heading level', 'kalahamoon'),
							value: String(attributes.headingLevel || 3),
							options: [
								{ label: 'H2', value: '2' },
								{ label: 'H3', value: '3' },
								{ label: 'H4', value: '4' },
								{ label: 'H5', value: '5' },
							],
							onChange: function (v) { setAttributes({ headingLevel: parseInt(v, 10) || 3 }); },
						})
					)
				),
				el('div', { className: 'kalahamoon-faq-edit-list' },
					items.map(function (item, i) {
						return el('div', { key: i, className: 'kalahamoon-faq-edit-row' },
							el(TextControl, {
								label: __('Question', 'kalahamoon'),
								value: item.q || '',
								onChange: function (v) { setAttributes({ items: updateItem(items, i, { q: v }) }); },
							}),
							el(TextareaControl, {
								label: __('Answer', 'kalahamoon'),
								value: item.a || '',
								onChange: function (v) { setAttributes({ items: updateItem(items, i, { a: v }) }); },
								rows: 3,
							}),
							el(Button, {
								variant: 'tertiary',
								isDestructive: true,
								onClick: function () { removeItem(i); },
							}, __('Remove', 'kalahamoon'))
						);
					})
				),
				el('div', { className: 'kalahamoon-faq-edit-actions' },
					el(Button, { variant: 'primary', onClick: addItem }, __('Add row', 'kalahamoon'))
				),
				el('p', { className: 'kalahamoon-faq-edit-note' },
					__('Live preview is approximate — final layout uses the theme’s tokens.', 'kalahamoon')
				)
			);
		},
	});
})(window.wp);
