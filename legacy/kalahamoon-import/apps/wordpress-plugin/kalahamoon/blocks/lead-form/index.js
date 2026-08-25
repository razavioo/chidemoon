(function (wp) {
	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var SelectControl = wp.components.SelectControl;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	registerBlockType('kalahamoon/lead-form', {
		edit: function (props) {
			var a = props.attributes;
			var set = props.setAttributes;
			return el('div', useBlockProps(),
				el(InspectorControls, null,
					el(PanelBody, { title: __('Content', 'kalahamoon'), initialOpen: true },
						el(SelectControl, {
							label: __('Request type', 'kalahamoon'),
							value: a.intent || 'contact',
							options: [
								{ label: __('Contact', 'kalahamoon'), value: 'contact' },
								{ label: __('Consultation', 'kalahamoon'), value: 'consultation' },
								{ label: __('Report an issue', 'kalahamoon'), value: 'issue' }
							],
							onChange: function (v) { set({ intent: v }); }
						}),
						el(TextControl, { label: __('Heading', 'kalahamoon'), value: a.heading || '', onChange: function (v) { set({ heading: v }); } }),
						el(TextControl, { label: __('Description', 'kalahamoon'), value: a.description || '', onChange: function (v) { set({ description: v }); } }),
						el(TextControl, { label: __('Button text', 'kalahamoon'), value: a.buttonText || '', onChange: function (v) { set({ buttonText: v }); } }),
						el(TextControl, { label: __('Success message', 'kalahamoon'), value: a.successText || '', onChange: function (v) { set({ successText: v }); } }),
						el(TextControl, { label: __('Consent text', 'kalahamoon'), value: a.consentText || '', onChange: function (v) { set({ consentText: v }); } })
					),
					el(PanelBody, { title: __('Fields', 'kalahamoon'), initialOpen: false },
						el(ToggleControl, { label: __('Subject', 'kalahamoon'), checked: a.showSubject !== false, onChange: function (v) { set({ showSubject: v }); } }),
						el(ToggleControl, { label: __('Name', 'kalahamoon'), checked: a.showName !== false, onChange: function (v) { set({ showName: v }); } }),
						el(ToggleControl, { label: __('Email', 'kalahamoon'), checked: a.showEmail !== false, onChange: function (v) { set({ showEmail: v }); } }),
						el(ToggleControl, { label: __('Phone', 'kalahamoon'), checked: a.showPhone !== false, onChange: function (v) { set({ showPhone: v }); } }),
						el(ToggleControl, { label: __('Message', 'kalahamoon'), checked: a.showMessage !== false, onChange: function (v) { set({ showMessage: v }); } })
					)
				),
				el(ServerSideRender, { block: 'kalahamoon/lead-form', attributes: a })
			);
		}
	});
})(window.wp);
