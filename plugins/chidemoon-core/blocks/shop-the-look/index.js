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
	var Spinner = wp.components.Spinner;
	var __ = wp.i18n.__;

	function parseHotspots(value) {
		return Array.isArray(value) ? value : [];
	}

	function clamp(value) {
		return Math.max(0, Math.min(100, Math.round(value)));
	}

	function ShopTheLookEditor(props) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
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

		function saveHotspots(next) {
			setAttributes({ hotspots: next });
		}

		function selectImage(media) {
			setAttributes({ imageId: media.id || 0, imageAlt: media.alt || '' });
		}

		function addHotspot(event) {
			if (!imageRef.current || event.target !== imageRef.current) return;
			var rect = imageRef.current.getBoundingClientRect();
			if (!rect.width || !rect.height) return;
			var next = hotspots.concat([{
				x: clamp(((event.clientX - rect.left) / rect.width) * 100),
				y: clamp(((event.clientY - rect.top) / rect.height) * 100),
				productId: 0,
				label: ''
			}]);
			saveHotspots(next);
			setActiveIndex(next.length - 1);
		}

		function updateHotspot(index, patch) {
			var next = hotspots.slice();
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
			if (spot.productId && !selectedProduct) options.push({ label: '#' + spot.productId, value: String(spot.productId) });
			return options;
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

		return el('div', blockProps,
			el(InspectorControls, null,
				el(PanelBody, { title: __('تنظیمات تصویر', 'chidemoon-core'), initialOpen: true },
					el(MediaUploadCheck, null, el(MediaUpload, { onSelect: selectImage, allowedTypes: ['image'], value: attributes.imageId, render: function (open) { return el(Button, { variant: 'secondary', onClick: open.open }, __('تعویض تصویر', 'chidemoon-core')); } })),
					el(TextControl, { label: __('متن جایگزین', 'chidemoon-core'), value: attributes.imageAlt, onChange: function (value) { setAttributes({ imageAlt: value }); } }),
					el(TextControl, { label: __('توضیح تصویر', 'chidemoon-core'), value: attributes.caption, onChange: function (value) { setAttributes({ caption: value }); } })
				),
				el(PanelBody, { title: __('نقاط محصولات', 'chidemoon-core'), initialOpen: true },
					el(TextControl, { label: __('جست‌وجوی محصول', 'chidemoon-core'), value: productQuery, onChange: setProductQuery, help: loadingProducts ? __('در حال جست‌وجو…', 'chidemoon-core') : __('فقط محصولات قابل‌خرید نمایش داده می‌شوند.', 'chidemoon-core') }),
					hotspots.length ? hotspots.map(function (spot, index) {
						return el('div', { key: index, className: 'chidemoon-shop-the-look-editor__item' },
							el('strong', null, '#' + (index + 1)),
							el(SelectControl, { label: __('محصول', 'chidemoon-core'), value: String(spot.productId || 0), options: productOptions(spot), onChange: function (value) { updateHotspot(index, { productId: parseInt(value, 10) || 0 }); } }),
							el(TextControl, { label: __('برچسب نقطه', 'chidemoon-core'), value: spot.label || '', onChange: function (value) { updateHotspot(index, { label: value }); } }),
							el(Button, { isDestructive: true, onClick: function () { removeHotspot(index); } }, __('حذف نقطه', 'chidemoon-core'))
						);
					}) : el('p', null, __('هنوز نقطه‌ای ثبت نشده است.', 'chidemoon-core'))
				)
			),
			el('figure', { className: 'chidemoon-shop-the-look-editor__canvas', onClick: addHotspot },
				el('img', { ref: imageRef, src: image.source_url, alt: attributes.imageAlt || image.alt_text || '', draggable: false }),
				hotspots.map(function (spot, index) {
					return el('button', { type: 'button', key: index, className: 'chidemoon-shop-the-look__hotspot', style: { left: clamp(spot.x) + '%', top: clamp(spot.y) + '%' }, onClick: function (event) { event.stopPropagation(); setActiveIndex(activeIndex === index ? null : index); }, 'aria-pressed': activeIndex === index ? 'true' : 'false', 'aria-label': spot.label || ('#' + (index + 1)) }, index + 1);
				}),
				el('figcaption', null, __('برای افزودن نقطه روی تصویر کلیک کنید.', 'chidemoon-core'))
			)
		);
	}

	registerBlockType('chidemoon/shop-the-look', { edit: ShopTheLookEditor, save: function () { return null; } });
})(window.wp);
