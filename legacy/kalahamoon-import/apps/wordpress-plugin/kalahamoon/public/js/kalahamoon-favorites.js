/**
 * Kalahamoon Favorites / Recently Viewed containers.
 * Hydrates any grid with data-kalahamoon-storage="favorites" or "recently_viewed".
 */
(function () {
	'use strict';

	var config = window.kalahamoonConfig || {};
	var restUrl = config.restUrl || '/wp-json/kalahamoon/v1/';
	var storageBackend = config.storageMode === 'session' ? window.sessionStorage : window.localStorage;

	function init() {
		var containers = Array.prototype.slice.call(document.querySelectorAll('[data-kalahamoon-storage]'));
		var legacy = document.getElementById('kalahamoon-favorites-container');
		if (legacy && containers.indexOf(legacy) === -1) containers.push(legacy);

		containers.forEach(function (container) {
			if (container.dataset.kalahamoonHydrated === '1') return;
			var storage = container.dataset.kalahamoonStorage || 'favorites';
			if (storage !== 'favorites' && storage !== 'recently_viewed') return;
			container.dataset.kalahamoonHydrated = '1';
			hydrate(container, storage);
		});
	}

	function hydrate(container, storage) {
		var key = storage === 'recently_viewed' ? 'kalahamoon_recently_viewed' : 'kalahamoon_favorites';
		var ids = safeRead(key);
		var limit = parseInt(container.dataset.kalahamoonLimit || ids.length || '0', 10);
		if (limit > 0) ids = ids.slice(0, limit);

		if (!ids.length) return;

		var empty = container.querySelector('.kalahamoon-favorites-empty, .kalahamoon-recently-viewed-empty');
		if (empty) empty.style.display = 'none';

		ids.forEach(function (id) {
			fetch(restUrl + 'products/' + encodeURIComponent(id))
				.then(function (response) { return response.ok ? response.json() : null; })
				.then(function (product) {
					if (!product) return;
					container.appendChild(renderCard(product, storage));
				})
				.catch(function () { /* Missing products should not break the whole list. */ });
		});
	}

	function renderCard(product, storage) {
		var item = document.createElement('div');
		item.className = 'kalahamoon-grid-item';
		var fallback = '<span class="kalahamoon-image-placeholder" aria-hidden="true">' +
			'<svg viewBox="0 0 200 200" width="100%" height="100%" focusable="false" aria-hidden="true">' +
				'<rect width="200" height="200" fill="currentColor" opacity="0.06"></rect>' +
				'<path d="M54 68h92v78H54zM72 54h56l18 14H54z" fill="none" stroke="currentColor" stroke-width="8" opacity="0.35"></path>' +
			'</svg></span>';
		var image = product.imageUrl
			? '<img src="' + esc(product.imageUrl) + '" alt="' + esc(product.title) + '" loading="lazy" decoding="async" />' + fallback.replace('aria-hidden="true"', 'aria-hidden="true" hidden')
			: fallback;

		var favoriteButton = storage === 'favorites'
			? '<button class="kalahamoon-favorite-btn is-favorited" type="button" data-product-id="' + esc(product.id) + '" aria-label="حذف از علاقه‌مندی‌ها">♥</button>'
			: '';

		item.innerHTML =
			'<div class="kalahamoon-product-card kalahamoon-layout-vertical" data-product-id="' + esc(product.id) + '">' +
				'<div class="kalahamoon-product-image">' +
					image +
					favoriteButton +
				'</div>' +
				'<div class="kalahamoon-product-info">' +
					'<h3 class="kalahamoon-product-title">' + esc(product.title) + '</h3>' +
					'<div class="kalahamoon-product-price"><span class="kalahamoon-current-price">' + esc(formatPrice(product.price, product.currency)) + '</span></div>' +
				'</div>' +
			'</div>';

		var img = item.querySelector('.kalahamoon-product-image img');
		if (img) {
			img.addEventListener('error', function () {
				img.hidden = true;
				var imageFallback = img.nextElementSibling;
				if (imageFallback) imageFallback.hidden = false;
			});
		}

		return item;
	}

	function safeRead(key) {
		try {
			var value = JSON.parse(storageBackend.getItem(key) || '[]');
			return Array.isArray(value) ? value.filter(Boolean) : [];
		} catch (e) {
			return [];
		}
	}

	function esc(value) {
		var d = document.createElement('div');
		d.textContent = value == null ? '' : String(value);
		return d.innerHTML;
	}

	function formatPrice(amount, currency) {
		var val = parseFloat(amount);
		if (!Number.isFinite(val) || val <= 0) return '—';
		if (currency === 'IRR') {
			val = Math.round(val / 10);
			return val.toLocaleString('en-US') + ' IRR';
		}
		return currency === 'EUR' ? '€' + val.toFixed(2) : '$' + val.toFixed(2);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
