(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var useRef = wp.element.useRef;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useSelect = wp.data.useSelect;
	var registerBlockType = wp.blocks.registerBlockType;
	var MediaUpload = wp.blockEditor.MediaUpload;
	var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var TextControl = wp.components.TextControl;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var Spinner = wp.components.Spinner;
	var __ = wp.i18n.__;

	function parseHotspots(value) {
		return Array.isArray(value) ? value : [];
	}

	function clamp(value) {
		var num = parseFloat(value);
		if (isNaN(num)) return 50;
		return Math.max(2, Math.min(98, Math.round(num * 10) / 10));
	}

	function clampInt(value) {
		return Math.max(0, Math.min(100, Math.round(parseFloat(value) || 0)));
	}

	function ShopTheLookEditor(props) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var canvasRef = useRef(null);
		var imageRef = useRef(null);
		var state = useState(null);
		var activeIndex = state[0];
		var setActiveIndex = state[1];
		var queryState = useState('');
		var productQuery = queryState[0];
		var setProductQuery = queryState[1];
		var productState = useState([]);
		var products = productState[0];
		var setProducts = productState[1];
		var loadingState = useState(false);
		var loadingProducts = loadingState[0];
		var setLoadingProducts = loadingState[1];
		var dragState = useState(null);
		var dragIndex = dragState[0];
		var setDragIndex = dragState[1];
		var hotspots = parseHotspots(attributes.hotspots);
		var image = useSelect(function (select) {
			return attributes.imageId ? select('core').getMedia(attributes.imageId) : null;
		}, [attributes.imageId]);
		var blockProps = useBlockProps({ className: 'chidemoon-shop-the-look-editor' });

		useEffect(function () {
			var active = true;
			var timer = window.setTimeout(function () {
				setLoadingProducts(true);
				wp.apiFetch({ path: productQuery ? '/chidemoon-core/v1/compare-products?search=' + encodeURIComponent(productQuery) : '/chidemoon-core/v1/compare-products?browse=1' })
					.then(function (response) { if (active) setProducts(Array.isArray(response) ? response : []); })
					.catch(function () { if (active) setProducts([]); })
					.finally(function () { if (active) setLoadingProducts(false); });
			}, 220);
			return function () { active = false; window.clearTimeout(timer); };
		}, [productQuery]);

		// Drag handling for repositioning hotspots via pointer
		useEffect(function () {
			if (dragIndex === null) return;
			function onMove(e) {
				var rect = canvasRef.current ? canvasRef.current.getBoundingClientRect() : null;
				if (!rect || !rect.width || !rect.height) return;
				var clientX = e.touches ? e.touches[0].clientX : e.clientX;
				var clientY = e.touches ? e.touches[0].clientY : e.clientY;
				var x = clamp(((clientX - rect.left) / rect.width) * 100);
				var y = clamp(((clientY - rect.top) / rect.height) * 100);
				var next = hotspots.slice();
				if (next[dragIndex]) {
					next[dragIndex] = Object.assign({}, next[dragIndex], { x: x, y: y });
					setAttributes({ hotspots: next });
				}
			}
			function onUp() { setDragIndex(null); }
			window.addEventListener('mousemove', onMove);
			window.addEventListener('mouseup', onUp);
			window.addEventListener('touchmove', onMove, { passive: false });
			window.addEventListener('touchend', onUp);
			return function () {
				window.removeEventListener('mousemove', onMove);
				window.removeEventListener('mouseup', onUp);
				window.removeEventListener('touchmove', onMove);
				window.removeEventListener('touchend', onUp);
			};
		}, [dragIndex, hotspots, setAttributes, setDragIndex]);

		function saveHotspots(next) {
			setAttributes({ hotspots: next });
		}

		function selectImage(media) {
			setAttributes({ imageId: media.id || 0, imageAlt: media.alt || '' });
		}

		function addHotspot(event) {
			// Only add when clicking directly on canvas background or image, not on hotspot button
			if (event.target.closest && event.target.closest('.chidemoon-shop-the-look__hotspot')) return;
			var rect = canvasRef.current ? canvasRef.current.getBoundingClientRect() : null;
			if (!rect || !rect.width || !rect.height) return;
			// Ignore if clicking inspector area? ensure within canvas
			var x = clamp(((event.clientX - rect.left) / rect.width) * 100);
			var y = clamp(((event.clientY - rect.top) / rect.height) * 100);
			var next = hotspots.concat([{
				x: x,
				y: y,
				productId: 0,
				label: ''
			}]);
			saveHotspots(next);
			setActiveIndex(next.length - 1);
		}

		function updateHotspot(index, patch) {
			var next = hotspots.slice();
			// sanitize x/y through clamp
			if ('x' in patch) patch.x = clamp(patch.x);
			if ('y' in patch) patch.y = clamp(patch.y);
			next[index] = Object.assign({}, next[index], patch);
			saveHotspots(next);
		}

		function removeHotspot(index) {
			saveHotspots(hotspots.filter(function (_, itemIndex) { return itemIndex !== index; }));
			setActiveIndex(null);
		}

		function productOptions(spot) {
			var selectedProduct = products.filter(function (product) { return Number(product.id) === Number(spot.productId); })[0];
			var options = [{ label: __('انتخاب محصول', 'chidemoon-core'), value: '0' }].concat(products.map(function (product) { return { label: product.title || ('#' + product.id), value: String(product.id) }; }));
			if (spot.productId && !selectedProduct) options.push({ label: '#' + spot.productId + ' (' + __('خارج از نتایج جستجو', 'chidemoon-core') + ')', value: String(spot.productId) });
			return options;
		}

		function productById(id) {
			return products.filter(function (p) { return Number(p.id) === Number(id); })[0] || null;
		}

		if (!attributes.imageId) {
			return el('div', blockProps,
				el('p', null, __('یک تصویر چیدمان انتخاب کنید، سپس روی محصولات آن کلیک کنید.', 'chidemoon-core')),
				el(MediaUploadCheck, null, el(MediaUpload, {
					onSelect: selectImage,
					allowedTypes: ['image'],
					render: function (open) { return el(Button, { variant: 'primary', onClick: open.open }, __('انتخاب تصویر', 'chidemoon-core')); }
				}))
			);
		}

		if (!image) {
			return el('div', blockProps, el(Spinner), el('p', null, __('در حال بارگذاری تصویر…', 'chidemoon-core')));
		}
		if (!image.source_url) {
			return el('div', blockProps, el('p', null, __('تصویر انتخاب‌شده در دسترس نیست. تصویر دیگری انتخاب کنید.', 'chidemoon-core')), el(MediaUploadCheck, null, el(MediaUpload, { onSelect: selectImage, allowedTypes: ['image'], render: function (open) { return el(Button, { variant: 'secondary', onClick: open.open }, __('تعویض تصویر', 'chidemoon-core')); } })));
		}

		var aiState = useState('');
		var aiStatus = aiState[0];
		var setAiStatus = aiState[1];

		function generateLook() {
			var ids = hotspots.map(function (spot) { return Number(spot.productId) || 0; }).filter(function (id) { return id > 0; });
			if (!ids.length && products.length) {
				ids = products.slice(0, 4).map(function (p) { return Number(p.id); });
			}
			if (!ids.length) {
				setAiStatus(__('اول یک محصول انتخاب کنید.', 'chidemoon-core'));
				return;
			}
			setAiStatus(__('در حال تولید صحنه با هوش مصنوعی…', 'chidemoon-core'));
			wp.apiFetch({
				path: '/chidemoon-ai/v1/jobs/look',
				method: 'POST',
				data: {
					product_ids: ids.slice(0, 6),
					room: '',
					style: 'minimal',
					instructions: __('Bright minimal styled room featuring these products', 'chidemoon-core'),
					rights_attestation: true
				}
			}).then(function (response) {
				var jobId = response && response.job ? response.job.id : 0;
				if (!jobId) {
					setAiStatus(__('تولید آغاز نشد.', 'chidemoon-core'));
					return;
				}
				setAiStatus(__('در صف تولید قرار گرفت (#' + jobId + '). بعد از تایید در Review Queue تصویر اینجا قرار می‌گیرد.', 'chidemoon-core'));
			}).catch(function () {
				setAiStatus(__('تولید ناموفق بود. از Look Studio تلاش کنید.', 'chidemoon-core'));
			});
		}

		// Helper to render hotspot editor controls
		function renderHotspotControls() {
			if (!hotspots.length) return el('p', null, __('هنوز نقطه‌ای ثبت نشده است. روی تصویر کلیک کنید تا نقطه اضافه شود، سپس محصول را انتخاب کنید. می‌توانید نقطه را بکشید تا جابجا شود.', 'chidemoon-core'));
			return hotspots.map(function (spot, index) {
				var isActive = activeIndex === index;
				var prod = productById(spot.productId);
				return el('div', { key: index, className: 'chidemoon-shop-the-look-editor__item' + (isActive ? ' is-active' : '') , style: { border: isActive ? '2px solid var(--wp-admin-theme-color, #3858e9)' : '1px solid #ddd', padding: '10px', borderRadius: '6px', marginBottom: '12px', background: isActive ? 'rgba(56,88,233,0.04)' : '#fff' } },
					el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' } },
						el('strong', { onClick: function () { setActiveIndex(isActive ? null : index); }, style: { cursor: 'pointer' } }, '#' + (index + 1) + (spot.label ? ' — ' + spot.label : '') + (prod ? ' (' + prod.title + ')' : '')),
						el('div', null,
							el(Button, { variant: isActive ? 'primary' : 'secondary', size: 'small', onClick: function () { setActiveIndex(isActive ? null : index); } }, isActive ? __('مخفی', 'chidemoon-core') : __('ویرایش', 'chidemoon-core')),
							el(Button, { isDestructive: true, size: 'small', style: { marginLeft: '6px' }, onClick: function () { removeHotspot(index); } }, __('حذف', 'chidemoon-core'))
						)
					),
					isActive ? el('div', null,
						prod && prod.image ? el('img', { src: prod.image, alt: '', style: { width: '44px', height: '44px', objectFit: 'cover', borderRadius: '4px', marginBottom: '8px', border: '1px solid #eee' } }) : null,
						el(SelectControl, { label: __('محصول', 'chidemoon-core'), value: String(spot.productId || 0), options: productOptions(spot), onChange: function (value) { updateHotspot(index, { productId: parseInt(value, 10) || 0 }); } }),
						el(TextControl, { label: __('برچسب نقطه', 'chidemoon-core'), help: __('مثال: مبل سه‌نفره، میز جلو مبلی', 'chidemoon-core'), value: spot.label || '', onChange: function (value) { updateHotspot(index, { label: value }); } }),
						RangeControl ? el(RangeControl, { label: __('موقعیت افقی (X %)', 'chidemoon-core'), value: clamp(spot.x), min: 2, max: 98, step: 0.5, onChange: function (val) { updateHotspot(index, { x: clamp(val) }); } }) : el(TextControl, { label: 'X %', type: 'number', value: String(clamp(spot.x)), onChange: function (val) { updateHotspot(index, { x: clamp(val) }); } }),
						RangeControl ? el(RangeControl, { label: __('موقعیت عمودی (Y %)', 'chidemoon-core'), value: clamp(spot.y), min: 2, max: 98, step: 0.5, onChange: function (val) { updateHotspot(index, { y: clamp(val) }); } }) : el(TextControl, { label: 'Y %', type: 'number', value: String(clamp(spot.y)), onChange: function (val) { updateHotspot(index, { y: clamp(val) }); } }),
						el('p', { style: { fontSize: '11px', color: '#666', margin: '6px 0 0' } }, __('نکته: نقطه را روی تصویر بکشید تا سریع جابجا شود. یا با اسلایدر دقیق تنظیم کنید.', 'chidemoon-core'))
					) : null
				);
			});
		}

		return el('div', blockProps,
			el(InspectorControls, null,
				el(PanelBody, { title: __('تنظیمات تصویر', 'chidemoon-core'), initialOpen: true },
					el(MediaUploadCheck, null, el(MediaUpload, { onSelect: selectImage, allowedTypes: ['image'], value: attributes.imageId, render: function (open) { return el(Button, { variant: 'secondary', onClick: open.open }, __('تعویض تصویر', 'chidemoon-core')); } })),
					el(TextControl, { label: __('متن جایگزین', 'chidemoon-core'), value: attributes.imageAlt, onChange: function (value) { setAttributes({ imageAlt: value }); } }),
					el(TextControl, { label: __('توضیح تصویر', 'chidemoon-core'), value: attributes.caption, onChange: function (value) { setAttributes({ caption: value }); } })
				),
				el(PanelBody, { title: __('تولید با هوش مصنوعی', 'chidemoon-core'), initialOpen: false },
					el('p', null, __('از محصولات انتخاب‌شده یک صحنه کامل بسازید. نتیجه در Review Queue تایید می‌شود.', 'chidemoon-core')),
					el(Button, { variant: 'primary', onClick: generateLook }, __('تولید صحنه با AI', 'chidemoon-core')),
					aiStatus ? el('p', { style: { marginTop: '8px', background: '#f0f0f0', padding: '8px', borderRadius: '4px' } }, aiStatus) : null
				),
				el(PanelBody, { title: __('نقاط محصولات (' + hotspots.length + ')', 'chidemoon-core'), initialOpen: true },
					el(TextControl, { label: __('جستجوی محصول', 'chidemoon-core'), placeholder: __('حداقل ۲ حرف…', 'chidemoon-core'), value: productQuery, onChange: setProductQuery, help: loadingProducts ? __('در حال جستجو…', 'chidemoon-core') : __('فقط محصولات قابل‌خرید نمایش داده می‌شوند. نقطه فعال را انتخاب کنید سپس محصول را برگزینید.', 'chidemoon-core') }),
					el('div', { style: { maxHeight: '260px', overflowY: 'auto', border: '1px solid #eee', borderRadius: '4px', padding: '6px', marginBottom: '12px', background: '#fafafa' } },
						products.length ? products.slice(0, 8).map(function (p) {
							return el('button', {
								key: p.id,
								type: 'button',
								onClick: function () {
									if (activeIndex !== null && hotspots[activeIndex]) {
										updateHotspot(activeIndex, { productId: Number(p.id), label: p.title });
									} else if (hotspots.length) {
										// attach to last hotspot if none active
										updateHotspot(hotspots.length -1, { productId: Number(p.id), label: p.title });
									}
								},
								style: { display: 'flex', alignItems: 'center', gap: '8px', width: '100%', padding: '6px', border: '1px solid #ddd', borderRadius: '4px', marginBottom: '4px', background: '#fff', cursor: 'pointer', textAlign: 'right' }
							},
								p.image ? el('img', { src: p.image, alt: '', style: { width: '32px', height: '32px', objectFit: 'cover', borderRadius: '3px' } }) : el('span', { style: { width: '32px', height: '32px', background: '#eee', borderRadius: '3px', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: '12px' } }, '#'),
								el('span', { style: { fontSize: '12px', flex: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' } }, p.title || ('#' + p.id))
							);
						}) : el('p', { style: { fontSize: '12px', color: '#666', margin: 0 } }, __('محصولی یافت نشد یا در حال بارگذاری…', 'chidemoon-core'))
					),
					renderHotspotControls()
				)
			),
			el('figure', { ref: canvasRef, className: 'chidemoon-shop-the-look-editor__canvas', onClick: addHotspot, style: { position: 'relative', cursor: 'crosshair', border: '2px dashed #ccc', borderRadius: '8px', overflow: 'hidden', background: '#f8f8f8' } },
				el('img', { ref: imageRef, src: image.source_url, alt: attributes.imageAlt || image.alt_text || '', draggable: false, style: { display: 'block', width: '100%', height: 'auto', pointerEvents: 'none' } }),
				hotspots.map(function (spot, index) {
					var isActive = activeIndex === index;
					var isDragging = dragIndex === index;
					return el('button', {
						type: 'button',
						key: index,
						className: 'chidemoon-shop-the-look__hotspot' + (isActive ? ' is-active' : '') + (isDragging ? ' is-dragging' : ''),
						style: {
							left: clamp(spot.x) + '%',
							top: clamp(spot.y) + '%',
							position: 'absolute',
							transform: 'translate(-50%,-50%)',
							zIndex: isActive ? 5 : 2,
							cursor: 'grab',
							boxShadow: isActive ? '0 0 0 3px rgba(56,88,233,0.3)' : 'none',
							opacity: isDragging ? 0.7 : 1
						},
						onClick: function (event) { event.stopPropagation(); setActiveIndex(isActive ? null : index); },
						onMouseDown: function (e) { e.stopPropagation(); e.preventDefault(); setDragIndex(index); setActiveIndex(index); },
						onTouchStart: function (e) { e.stopPropagation(); setDragIndex(index); setActiveIndex(index); },
						'aria-pressed': isActive ? 'true' : 'false',
						'aria-label': spot.label || ('#' + (index + 1) + ' — ' + (productById(spot.productId) ? productById(spot.productId).title : __('بدون محصول', 'chidemoon-core')))
					}, index + 1);
				}),
				el('figcaption', { style: { position: 'absolute', bottom: '8px', left: '8px', background: 'rgba(0,0,0,0.65)', color: '#fff', padding: '4px 8px', borderRadius: '4px', fontSize: '11px', pointerEvents: 'none' } }, __('برای افزودن نقطه روی تصویر کلیک کنید — نقطه را بکشید تا جابجا شود.', 'chidemoon-core'))
			),
			el('p', { style: { fontSize: '12px', color: '#666', marginTop: '8px' } }, hotspots.length ? __('نقطه فعال را در پنل سمت چپ ویرایش کنید. هر نقطه یک محصول را نشان می‌دهد.', 'chidemoon-core') : __('هنوز نقطه‌ای ندارید. روی تصویر کلیک کنید.', 'chidemoon-core'))
		);
	}

	registerBlockType('chidemoon/shop-the-look', { edit: ShopTheLookEditor, save: function () { return null; } });
})(window.wp);
