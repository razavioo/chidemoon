/**
 * Kalahamoon Click Tracker — Lightweight product-link tracking via sendBeacon.
 * ~1.5KB unminified. No jQuery. No frameworks.
 */
(function () {
	'use strict';

	var config = window.kalahamoonConfig || {};
	var restUrl = config.restUrl || '/wp-json/kalahamoon/v1/';
	var storage = null;
	var volatileStorage = {};
	try {
		storage = config.storageMode === 'session' ? window.sessionStorage : window.localStorage;
	} catch (error) {
		// Private browsing policies may block storage; tracking still works.
	}

	function setFavoriteButton(btn, selected) {
		btn.classList.toggle('is-active', selected);
		btn.classList.toggle('is-favorited', selected);
		btn.setAttribute('aria-pressed', selected ? 'true' : 'false');
		btn.setAttribute('aria-label', selected
			? (btn.dataset.labelRemove || 'Remove from favorites')
			: (btn.dataset.labelSave || 'Save to favorites'));
	}

	function writeLocalList(key, value) {
		volatileStorage[key] = value.slice();
		try {
			if (storage) storage.setItem(key, JSON.stringify(value));
		} catch (error) {
			// The in-memory copy keeps controls coherent for the current page.
		}
	}

	document.addEventListener('click', function (e) {
		var link = e.target.closest('.kalahamoon-product-link');
		if (!link) return;

		var data = {
			productId: link.dataset.productId || '',
			linkId: link.dataset.linkId || '',
			postId: link.dataset.postId || '',
			blockType: link.dataset.blockType || '',
		};

		// Use sendBeacon for non-blocking tracking
		if (navigator.sendBeacon) {
			navigator.sendBeacon(
				restUrl + 'clicks',
				new Blob([JSON.stringify(data)], { type: 'application/json' })
			);
		}
	});

	// Favorites toggle
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.kalahamoon-favorite-btn');
		if (!btn) return;

		e.preventDefault();
		e.stopPropagation();

		var id = btn.dataset.productId;
		if (!id) return;

		var favs = safeRead('kalahamoon_favorites');
		var index = favs.indexOf(id);

		if (index > -1) {
			favs.splice(index, 1);
			setFavoriteButton(btn, false);
		} else {
			favs.push(id);
			setFavoriteButton(btn, true);
		}

		writeLocalList('kalahamoon_favorites', favs);
	});

	// Mark already-favorited products on page load
	var favs = safeRead('kalahamoon_favorites');
	if (favs.length) {
		document.querySelectorAll('.kalahamoon-favorite-btn').forEach(function (btn) {
			if (favs.indexOf(btn.dataset.productId) > -1) {
				setFavoriteButton(btn, true);
			}
		});
	}

	// Privacy-friendly recently viewed list: keep only local product ids.
	document.querySelectorAll('.kalahamoon-product-card[data-product-id][data-track-recent="1"]').forEach(function (card) {
		var id = card.dataset.productId;
		if (!id) return;
		var recent = safeRead('kalahamoon_recently_viewed').filter(function (item) { return item !== id; });
		recent.unshift(id);
		writeLocalList('kalahamoon_recently_viewed', recent.slice(0, 12));
	});

	function safeRead(key) {
		try {
			if (!storage) return (volatileStorage[key] || []).slice();
			var value = JSON.parse(storage.getItem(key) || '[]');
			return Array.isArray(value) ? value : [];
		} catch (e) {
			return (volatileStorage[key] || []).slice();
		}
	}
})();
