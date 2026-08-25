/**
 * Kalahamoon Shop-the-Look — Gutenberg block editor script.
 *
 * Editor UX:
 *  1. Upload / select an image from Media Library.
 *  2. Click anywhere on the image to add a hotspot at that position.
 *  3. The picker opens automatically to assign a product to the new hotspot.
 *  4. Existing hotspots show as numbered dots; click them to reassign or remove.
 */
(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useRef = wp.element.useRef;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var MediaUpload = wp.blockEditor.MediaUpload;
	var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;
	var SelectControl = wp.components.SelectControl;
	var Popover = wp.components.Popover;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var __ = wp.i18n.__;

	function parseHotspots(str) {
		try { return JSON.parse(str || '[]'); } catch (e) { return []; }
	}

	function ShopTheLookEditor(props) {
		var a = props.attributes;
		var setAttributes = props.setAttributes;
		var blockProps = useBlockProps();
		var imgRef = useRef(null);
		var _s = useState(null); var activeIdx = _s[0]; var setActiveIdx = _s[1];

		var hotspots = parseHotspots(a.hotspots);

		function saveHotspots(arr) {
			setAttributes({ hotspots: JSON.stringify(arr) });
		}

		function handleImageClick(e) {
			if (!imgRef.current) return;
			var rect = imgRef.current.getBoundingClientRect();
			var x = Math.round(((e.clientX - rect.left) / rect.width) * 100);
			var y = Math.round(((e.clientY - rect.top) / rect.height) * 100);
			var newSpot = { x: x, y: y, productId: '', style: 'dot', label: '' };
			var updated = hotspots.concat([newSpot]);
			saveHotspots(updated);
			var newIdx = updated.length - 1;
			setActiveIdx(newIdx);
			// Open picker for the new hotspot
			if (window.kalahamoonPicker) {
				window.kalahamoonPicker.open({
					multiple: false,
					title: __('Assign a product to this hotspot', 'kalahamoon'),
					onSelect: function (id) {
						var arr = parseHotspots(JSON.stringify(updated));
						arr[newIdx] = Object.assign({}, arr[newIdx], { productId: id });
						saveHotspots(arr);
						setActiveIdx(null);
					},
				});
			}
		}

		function updateHotspot(idx, patch) {
			var arr = hotspots.slice();
			arr[idx] = Object.assign({}, arr[idx], patch);
			saveHotspots(arr);
		}

		function removeHotspot(idx) {
			var arr = hotspots.filter(function (_, i) { return i !== idx; });
			saveHotspots(arr);
			setActiveIdx(null);
		}

		function reassign(idx) {
			if (window.kalahamoonPicker) {
				window.kalahamoonPicker.open({
					multiple: false,
					initialIds: hotspots[idx].productId ? [hotspots[idx].productId] : [],
					title: __('Reassign product for this hotspot', 'kalahamoon'),
					onSelect: function (id) { updateHotspot(idx, { productId: id }); },
				});
			}
		}

		// ── No image yet ──────────────────────────────────────────────────────
		if (!a.imageUrl) {
			return el('div', blockProps,
				el('div', { style: { padding: '60px 20px', textAlign: 'center', background: '#f9fafb', border: '2px dashed #e5e7eb', borderRadius: '12px', color: '#6b7280' } },
					el('p', { style: { marginBottom: '16px', fontSize: '15px' } },
						__('Upload a lifestyle image, then click on it to add product hotspots.', 'kalahamoon')
					),
					el(MediaUploadCheck, null,
						el(MediaUpload, {
							onSelect: function (media) {
								setAttributes({ imageUrl: media.url, imageAlt: media.alt || '', imageId: media.id || 0 });
							},
							allowedTypes: ['image'],
							render: function (ref) {
								return el(Button, { variant: 'primary', onClick: ref.open }, __('Upload image', 'kalahamoon'));
							},
						})
					)
				)
			);
		}

		// ── Image with hotspots ───────────────────────────────────────────────
		return el('div', blockProps,
			el(InspectorControls, null,
				el(PanelBody, { title: __('Image', 'kalahamoon'), initialOpen: true },
					el(MediaUploadCheck, null,
						el(MediaUpload, {
							onSelect: function (media) {
								setAttributes({ imageUrl: media.url, imageAlt: media.alt || '', imageId: media.id || 0 });
							},
							allowedTypes: ['image'],
							value: a.imageId,
							render: function (ref) {
								return el(Button, { variant: 'secondary', onClick: ref.open }, __('Replace image', 'kalahamoon'));
							},
						})
					),
					el(TextControl, {
						label: __('Alt text', 'kalahamoon'),
						value: a.imageAlt,
						onChange: function (v) { setAttributes({ imageAlt: v }); },
					}),
					el(TextControl, {
						label: __('Caption (optional)', 'kalahamoon'),
						value: a.caption,
						onChange: function (v) { setAttributes({ caption: v }); },
					}),
					el(SelectControl, {
						label: __('Display style', 'kalahamoon'),
						value: a.displayStyle || 'hotspots',
						help: __('How pinned products are presented on the published page.', 'kalahamoon'),
						options: [
							{ label: __('Hotspots on image (overlay cards)', 'kalahamoon'), value: 'hotspots' },
							{ label: __('Image + product strip below (carousel)', 'kalahamoon'), value: 'strip' },
							{ label: __('Side-by-side (image + product list)', 'kalahamoon'), value: 'side' },
							{ label: __('Numbered product list under image', 'kalahamoon'), value: 'list' },
						],
						onChange: function (v) { setAttributes({ displayStyle: v }); },
					})
				),
				hotspots.length > 0
					? el(PanelBody, { title: __('Hotspots', 'kalahamoon') + ' (' + hotspots.length + ')', initialOpen: true },
						hotspots.map(function (hs, idx) {
							return el('div', {
								key: idx,
								style: { marginBottom: '12px', padding: '10px', background: '#f9fafb', borderRadius: '8px', border: '1px solid #e5e7eb' },
							},
								el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' } },
									el('strong', { style: { fontSize: '13px' } }, '#' + (idx + 1) + ' — ' + (hs.productId || __('no product', 'kalahamoon'))),
									el(Button, { variant: 'tertiary', isDestructive: true, onClick: function () { removeHotspot(idx); }, style: { padding: '0 6px', minWidth: 0 } }, __('Remove', 'kalahamoon'))
								),
								el(Button, { variant: 'secondary', onClick: function () { reassign(idx); }, style: { width: '100%', justifyContent: 'center', marginBottom: '6px' } },
									hs.productId ? __('Reassign product…', 'kalahamoon') : __('Assign product…', 'kalahamoon')
								),
								el(SelectControl, {
									label: __('Style', 'kalahamoon'),
									value: hs.style || 'dot',
									options: [
										{ label: __('Dot', 'kalahamoon'), value: 'dot' },
										{ label: __('Plus (+)', 'kalahamoon'), value: 'plus' },
										{ label: __('Number', 'kalahamoon'), value: 'number' },
									],
									onChange: function (v) { updateHotspot(idx, { style: v }); },
								})
							);
						})
					)
					: null
			),
			el('figure', { className: 'kalahamoon-stl-figure', style: { position: 'relative', margin: 0, cursor: 'crosshair' } },
				el('img', {
					ref: imgRef,
					src: a.imageUrl,
					alt: a.imageAlt || '',
					style: { width: '100%', height: 'auto', display: 'block', borderRadius: '12px', userSelect: 'none' },
					onClick: handleImageClick,
					draggable: false,
				}),
				hotspots.map(function (hs, idx) {
					return el('button', {
						key: idx,
						className: 'kalahamoon-stl-dot kalahamoon-stl-dot--' + (hs.style || 'dot') + (hs.productId ? '' : ' kalahamoon-stl-dot--empty'),
						style: { left: hs.x + '%', top: hs.y + '%' },
						onClick: function (e) { e.stopPropagation(); setActiveIdx(activeIdx === idx ? null : idx); },
						title: hs.productId || __('No product yet', 'kalahamoon'),
					},
						hs.style === 'number' ? (idx + 1) : (hs.style === 'plus' ? '+' : '')
					);
				}),
				el('div', { style: { position: 'absolute', insetBlockStart: '10px', insetInlineEnd: '10px', background: 'rgba(0,0,0,.55)', color: '#fff', borderRadius: '8px', padding: '6px 12px', fontSize: '12px', pointerEvents: 'none' } },
					__('Click image to add hotspots', 'kalahamoon')
				),
				a.caption
					? el('figcaption', { style: { textAlign: 'center', fontSize: '13px', marginBlockStart: '8px', color: '#6b7280' } }, a.caption)
					: null
			),
			// ── Pinned products list (below the image) ───────────────────────────
			// Gives the editor an at-a-glance view of what has been pinned, with
			// inline remove/reassign actions — without opening the inspector.
			el('div', { className: 'kalahamoon-stl-pinned' },
				el('div', { style: { fontSize: '12px', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '.04em', color: '#6b7280', margin: '14px 0 8px' } },
					__('Pinned products', 'kalahamoon') + ' (' + hotspots.length + ')'
				),
				hotspots.length === 0
					? el('p', { style: { fontSize: '13px', color: '#9ca3af', margin: 0 } },
						__('No products pinned yet — click on the image to add a hotspot.', 'kalahamoon')
					)
					: el('ul', { style: { listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: '6px' } },
						hotspots.map(function (hs, idx) {
							return el('li', {
								key: idx,
								style: { display: 'flex', alignItems: 'center', gap: '8px', padding: '6px 8px', background: '#f9fafb', border: '1px solid #e5e7eb', borderRadius: '8px' },
							},
								el('span', {
									style: { flex: '0 0 22px', width: '22px', height: '22px', borderRadius: '50%', background: hs.productId ? '#2563eb' : '#9ca3af', color: '#fff', fontSize: '12px', fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center' },
								}, idx + 1),
								el('span', { style: { flex: 1, fontSize: '12px', direction: 'ltr', wordBreak: 'break-all', color: hs.productId ? '#111827' : '#9ca3af' } },
									hs.productId || __('No product assigned', 'kalahamoon')
								),
								el(Button, { variant: 'secondary', size: 'small', onClick: function () { reassign(idx); } },
									hs.productId ? __('Change', 'kalahamoon') : __('Assign', 'kalahamoon')
								),
								el(Button, { variant: 'tertiary', isDestructive: true, size: 'small', onClick: function () { removeHotspot(idx); } },
									__('Remove', 'kalahamoon')
								)
							);
						})
					)
			)
		);
	}

	registerBlockType('kalahamoon/shop-the-look', {
		edit: ShopTheLookEditor,
	});
})(window.wp);
