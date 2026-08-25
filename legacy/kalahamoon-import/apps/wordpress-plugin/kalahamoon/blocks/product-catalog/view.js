/**
 * Kalahamoon Product Catalog — local comparison selection.
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'kalahamoon_compare';
	var MAX_PRODUCTS = 4;

	function readSelection() {
		try {
			var parsed = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '[]');
			if (!Array.isArray(parsed)) return [];

			return parsed.filter(function (item) {
				return item && typeof item.id === 'string' && item.id !== '' &&
					typeof item.type === 'string' && item.type !== '';
			}).slice(0, MAX_PRODUCTS);
		} catch (error) {
			return [];
		}
	}

	function writeSelection(selection) {
		try {
			window.localStorage.setItem(STORAGE_KEY, JSON.stringify(selection));
		} catch (error) {
			// Comparison remains usable for this page when storage is unavailable.
		}
		window.dispatchEvent(new CustomEvent('kalahamoon_compare_updated', { detail: { count: selection.length } }));
	}

	function initCatalog(catalog) {
		if (catalog.dataset.kalahamoonCatalogReady === '1') return;
		catalog.dataset.kalahamoonCatalogReady = '1';

		var tray = catalog.querySelector('[data-compare-tray]');
		var status = catalog.querySelector('[data-compare-status]');
		var link = catalog.querySelector('[data-compare-link]');
		var clear = catalog.querySelector('[data-compare-clear]');
		var buttons = catalog.querySelectorAll('.kalahamoon-catalog-card__compare');
		var selection = readSelection();

		function announce(message) {
			if (!status) return;
			status.textContent = message;
		}

		function render(message) {
			var ids = selection.map(function (item) { return item.id; });

			buttons.forEach(function (button) {
				var selected = ids.indexOf(button.dataset.productId || '') !== -1;
				button.setAttribute('aria-pressed', selected ? 'true' : 'false');
				button.classList.toggle('is-selected', selected);
			});

			if (tray) tray.hidden = selection.length === 0;
			if (link) {
				var baseUrl = catalog.dataset.compareUrl || link.href;
				var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
				link.href = baseUrl + separator + 'products=' + encodeURIComponent(ids.join(','));
				link.hidden = selection.length < 2;
				link.setAttribute('aria-disabled', selection.length < 2 ? 'true' : 'false');
			}

			announce(message || (catalog.dataset.compareSelected || '{count} selected').replace('{count}', String(selection.length)));
		}

		buttons.forEach(function (button) {
			button.addEventListener('click', function () {
				var id = button.dataset.productId || '';
				var type = button.dataset.comparisonType || '';
				var existingIndex = selection.findIndex(function (item) { return item.id === id; });

				if (existingIndex !== -1) {
					selection.splice(existingIndex, 1);
					writeSelection(selection);
					render();
					return;
				}

				if (selection.length && selection[0].type !== type) {
					render(catalog.dataset.compareMixed || 'Choose products from the same comparison type.');
					return;
				}

				if (selection.length >= MAX_PRODUCTS) {
					render(catalog.dataset.compareMaximum || 'You can compare up to four products.');
					return;
				}

				selection.push({ id: id, type: type });
				writeSelection(selection);
				render();
			});
		});

		if (clear) {
			clear.addEventListener('click', function () {
				selection = [];
				writeSelection(selection);
				render();
			});
		}

		render();
	}

	function init() {
		document.querySelectorAll('[data-kalahamoon-catalog="1"]').forEach(initCatalog);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
