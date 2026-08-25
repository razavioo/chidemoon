/**
 * Kalahamoon Product Picker — shared modal used by blocks (and TinyMCE plugin) to
 * search the cached Kalahamoon products and insert an id (or list of ids).
 *
 * Exposes: window.kalahamoonPicker.open({ multiple, initialIds, onSelect, title })
 *
 * Uses the public REST route /kalahamoon/v1/products?search=&limit= registered in
 * Kalahamoon_REST_Controller::get_products(). No auth — cache is already local.
 *
 * No build step: plain JS using globals (wp.element, wp.components, wp.i18n).
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.element || !wp.components) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var Modal = wp.components.Modal;
	var Button = wp.components.Button;
	var SearchControl = wp.components.SearchControl;
	var Spinner = wp.components.Spinner;
	var Notice = wp.components.Notice;
	var __ = wp.i18n.__;
	var render = wp.element.render;
	var createRoot = wp.element.createRoot;

	var pickerConfig = window.kalahamoonPickerConfig || {};
	var html = document.documentElement;
	var restBase = pickerConfig.restUrl
		|| (window.wpApiSettings && window.wpApiSettings.root + 'kalahamoon/v1/')
		|| '/wp-json/kalahamoon/v1/';
	var nonce = pickerConfig.nonce
		|| (window.wpApiSettings && window.wpApiSettings.nonce)
		|| '';
	var direction = pickerConfig.direction || (pickerConfig.isRtl ? 'rtl' : 'ltr');
	var language = pickerConfig.language || html.lang || (direction === 'rtl' ? 'fa-IR' : 'en');
	var locale = pickerConfig.locale || language;
	var displayUnit = pickerConfig.displayUnit || 'TOMAN';

	var platformColors = {
		bakalahamoon: '#ff6b35', digikala: '#ef394e', torob: '#00b4d8',
		woocommerce: '#7c3aed', default: '#6b7280',
	};

	function pickerMonogram(title, platform) {
		// Returns an SVG element for the monogram fallback thumbnail.
		var color = platformColors[(platform || '').toLowerCase()] || platformColors.default;
		var letter = (title || '?').replace(/^\s+/, '').charAt(0);
		return el('svg', {
			viewBox: '0 0 80 60', xmlns: 'http://www.w3.org/2000/svg',
			width: '100%', height: '100%', style: { display: 'block' },
		},
			el('rect', { width: 80, height: 60, fill: color + '22' }),
			el('text', {
				x: 40, y: 40, textAnchor: 'middle', fontSize: 28,
				fontFamily: 'system-ui,sans-serif', fill: color, fontWeight: 700,
			}, letter)
		);
	}

	function formatPrice(p) {
		if (!p) return '';
		try {
			return new Intl.NumberFormat(language || locale || undefined).format(p);
		} catch (e) {
			return String(p);
		}
	}

	function currencyLabel(currency) {
		if (currency === 'IRR') {
			return displayUnit === 'RIAL' ? __('Rial', 'kalahamoon') : __('Toman', 'kalahamoon');
		}
		return currency || '';
	}

	function fetchProducts(search, signal) {
		var separator = restBase.indexOf('?') === -1 ? '?' : '&';
		var url = restBase + 'products' + separator + 'limit=24&search=' + encodeURIComponent(search || '');
		return fetch(url, {
			headers: { 'X-WP-Nonce': nonce },
			signal: signal,
			credentials: 'same-origin',
		}).then(function (r) {
			if (!r.ok) throw new Error('HTTP ' + r.status);
			return r.json();
		}).then(function (data) {
			// REST returns { items, total } — items is array of product arrays.
			return (data && data.items) || [];
		});
	}

	function PickerApp(props) {
		var multiple = !!props.multiple;
		var _useState1 = useState(''); var search = _useState1[0]; var setSearch = _useState1[1];
		var _useState2 = useState([]); var items = _useState2[0]; var setItems = _useState2[1];
		var _useState3 = useState(false); var loading = _useState3[0]; var setLoading = _useState3[1];
		var _useState4 = useState(null); var error = _useState4[0]; var setError = _useState4[1];
		var _useState5 = useState(props.initialIds || []); var selected = _useState5[0]; var setSelected = _useState5[1];
		var debounceRef = useRef(null);
		var abortRef = useRef(null);

		useEffect(function () {
			if (debounceRef.current) clearTimeout(debounceRef.current);
			debounceRef.current = setTimeout(function () {
				if (abortRef.current) abortRef.current.abort();
				var controller = ('AbortController' in window) ? new AbortController() : null;
				abortRef.current = controller;
				setLoading(true);
				setError(null);
				fetchProducts(search, controller ? controller.signal : undefined)
					.then(function (list) { setItems(list); setLoading(false); })
					.catch(function (err) {
						if (err && err.name === 'AbortError') return;
						setError(err.message || __('Error loading products.', 'kalahamoon'));
						setLoading(false);
					});
			}, 280);
			return function () {
				if (debounceRef.current) clearTimeout(debounceRef.current);
			};
		}, [search]);

		function toggle(id) {
			if (multiple) {
				setSelected(function (prev) {
					return prev.indexOf(id) >= 0
						? prev.filter(function (x) { return x !== id; })
						: prev.concat([id]);
				});
			} else {
				props.onSelect([id]);
				props.onClose();
			}
		}

		function confirmMulti() {
			props.onSelect(selected);
			props.onClose();
		}

		var resultsContent;
		if (loading && items.length === 0) {
			resultsContent = el('div', { className: 'kalahamoon-picker-center' }, el(Spinner, null));
		} else if (error) {
			resultsContent = el(Notice, { status: 'error', isDismissible: false }, error);
		} else if (items.length === 0) {
			resultsContent = el('div', { className: 'kalahamoon-picker-empty' },
				__('No products found. Try a different search or sync products from the Kalahamoon dashboard.', 'kalahamoon')
			);
		} else {
			resultsContent = el('ul', { className: 'kalahamoon-picker-grid' },
				items.map(function (p) {
					var id = p.id || '';
					var img = p.imageUrl || p.image_url || '';
					var marketplace = p.platform || p.marketplace || '';
					var isSel = selected.indexOf(id) >= 0;
					return el('li', {
						key: id,
						className: 'kalahamoon-picker-card' + (isSel ? ' is-selected' : ''),
						onClick: function () { toggle(id); },
					},
						el('div', { className: 'kalahamoon-picker-thumb' },
							img
								? el('img', {
									src: img, alt: p.title || '',
									onError: function(e) {
										e.target.style.display = 'none';
										var fb = e.target.nextSibling;
										if (fb) fb.style.display = 'flex';
									}
								  })
								: null,
							(!img || true) ? el('div', {
								className: 'kalahamoon-picker-thumb-fallback',
								style: { display: img ? 'none' : 'flex' },
							}, pickerMonogram(p.title || '', p.platform || '')) : null
						),
						el('div', { className: 'kalahamoon-picker-meta' },
							el('div', { className: 'kalahamoon-picker-title', dir: 'auto' }, p.title || id),
							el('div', { className: 'kalahamoon-picker-price' },
								p.price ? formatPrice(p.price) + ' ' + currencyLabel(p.currency) : '—',
								marketplace ? el('span', { className: 'kalahamoon-picker-badge', dir: 'auto' }, marketplace) : null
							)
						),
						isSel ? el('div', { className: 'kalahamoon-picker-check' }, '✓') : null
					);
				})
			);
		}

		return el(Modal, {
			title: props.title || __('Pick a Kalahamoon product', 'kalahamoon'),
			onRequestClose: props.onClose,
			className: 'kalahamoon-picker-modal',
			isFullScreen: false,
			shouldCloseOnClickOutside: true,
		},
			el('div', { className: 'kalahamoon-picker-shell', dir: direction, lang: language },
				el(SearchControl, {
					value: search,
					onChange: setSearch,
					placeholder: __('Search products by name…', 'kalahamoon'),
				}),
				multiple ? el('div', { className: 'kalahamoon-picker-selcount' },
					selected.length
						? (selected.length + ' ' + __('selected', 'kalahamoon'))
						: __('Click to add products to your selection', 'kalahamoon')
				) : null,
				el('div', { className: 'kalahamoon-picker-results' }, resultsContent),
				multiple
					? el('div', { className: 'kalahamoon-picker-footer' },
						el(Button, { variant: 'tertiary', onClick: props.onClose }, __('Cancel', 'kalahamoon')),
						el(Button, {
							variant: 'primary',
							disabled: selected.length === 0,
							onClick: confirmMulti,
						}, __('Insert', 'kalahamoon') + (selected.length ? ' (' + selected.length + ')' : ''))
					)
					: null
			)
		);
	}

	var mountNode = null;
	var root = null;

	function open(options) {
		options = options || {};
		close();

		mountNode = document.createElement('div');
		mountNode.className = 'kalahamoon-picker-root';
		mountNode.setAttribute('dir', direction);
		mountNode.setAttribute('lang', language);
		document.body.appendChild(mountNode);

		function handleClose() { close(); }
		function handleSelect(ids) {
			if (typeof options.onSelect === 'function') {
				options.onSelect(options.multiple ? ids : (ids[0] || ''));
			}
		}

		var initialIds = [];
		if (options.initialIds) {
			initialIds = Array.isArray(options.initialIds)
				? options.initialIds.slice()
				: String(options.initialIds).split(',').map(function (s) { return s.trim(); }).filter(Boolean);
		}

		var app = el(PickerApp, {
			multiple: !!options.multiple,
			initialIds: initialIds,
			title: options.title,
			onSelect: handleSelect,
			onClose: handleClose,
		});

		if (createRoot) {
			root = createRoot(mountNode);
			root.render(app);
		} else if (render) {
			render(app, mountNode);
		}
	}

	function close() {
		if (root) {
			try { root.unmount(); } catch (e) {}
			root = null;
		} else if (mountNode && render) {
			try { wp.element.unmountComponentAtNode && wp.element.unmountComponentAtNode(mountNode); } catch (e) {}
		}
		if (mountNode && mountNode.parentNode) {
			mountNode.parentNode.removeChild(mountNode);
		}
		mountNode = null;
	}

	window.kalahamoonPicker = { open: open, close: close };
})(window.wp);
