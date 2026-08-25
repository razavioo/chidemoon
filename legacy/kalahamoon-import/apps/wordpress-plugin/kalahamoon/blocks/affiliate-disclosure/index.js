(function (wp) {
	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextareaControl = wp.components.TextareaControl;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	registerBlockType('kalahamoon/affiliate-disclosure', {
		edit: function (props) {
			var a = props.attributes;
			var setAttributes = props.setAttributes;
			return el('div', useBlockProps(),
				el(InspectorControls, null,
					el(PanelBody, { title: __('Disclosure', 'kalahamoon'), initialOpen: true },
						el(TextareaControl, {
							label: __('Custom text', 'kalahamoon'),
							help: __('Leave empty to use the saved disclosure text from plugin settings.', 'kalahamoon'),
							value: a.text || '',
							onChange: function (v) { setAttributes({ text: v }); }
						})
					)
				),
				el(ServerSideRender, { block: 'kalahamoon/affiliate-disclosure', attributes: a })
			);
		}
	});
})(window.wp);
