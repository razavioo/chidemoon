/**
 * Kalahamoon — Admin JavaScript
 * Vanilla JS, no jQuery dependency.
 */
(function () {
	'use strict';

	var __ = window.wp && window.wp.i18n && window.wp.i18n.__
		? window.wp.i18n.__
		: function (text) { return text; };
	var adminConfig = window.kalahamoonAdminConfig || {};
	var direction = adminConfig.direction || (adminConfig.isRtl ? 'rtl' : 'ltr');
	var language = adminConfig.language || document.documentElement.lang || 'en';

	/* ── Copy product ID to clipboard ── */
	function initCopyProductId() {
		document.querySelectorAll('.kalahamoon-product-id-cell').forEach(function (cell) {
			cell.addEventListener('click', function () {
				var code = cell.querySelector('code');
				if (!code) return;

				var text = code.textContent.trim();
				navigator.clipboard.writeText(text).then(function () {
					showTooltip(cell, __('Copied!', 'kalahamoon'));
				}).catch(function () {
					// Fallback for older browsers
					var range = document.createRange();
					range.selectNodeContents(code);
					var sel = window.getSelection();
					sel.removeAllRanges();
					sel.addRange(range);
					try {
						document.execCommand('copy');
						showTooltip(cell, __('Copied!', 'kalahamoon'));
					} catch (_) {
						// silent fail
					}
					sel.removeAllRanges();
				});
			});
		});
	}

	function showTooltip(anchor, message) {
		var existing = anchor.querySelector('.kalahamoon-copy-tooltip');
		if (existing) existing.remove();

		var tip = document.createElement('span');
		tip.className = 'kalahamoon-copy-tooltip';
		tip.textContent = message;
		tip.dir = direction;
		tip.lang = language;
		anchor.style.position = 'relative';
		anchor.appendChild(tip);

		requestAnimationFrame(function () {
			tip.classList.add('is-visible');
		});

		setTimeout(function () {
			tip.classList.remove('is-visible');
			setTimeout(function () { tip.remove(); }, 200);
		}, 1200);
	}

	/* ── Refresh analytics via AJAX ── */
	function initAnalyticsRefresh() {
		var btn = document.getElementById('kalahamoon-refresh-analytics');
		if (!btn) return;

		btn.addEventListener('click', function () {
			var status = document.getElementById('kalahamoon-action-status');
			if (!status) return;

			var spinner = btn.querySelector('.kalahamoon-sync-spinner');
			if (spinner) spinner.style.display = 'inline-block';
			btn.disabled = true;
			status.textContent = '';

			fetch(window.ajaxurl + '?action=kalahamoon_sync_now&_wpnonce=' + encodeURIComponent(btn.dataset.nonce || ''))
				.then(function (res) { return res.json(); })
				.then(function (data) {
					if (data.success) {
						status.textContent = data.data.message || __('Refreshed', 'kalahamoon');
						// Reload after short delay so updated stats are visible
						setTimeout(function () { window.location.reload(); }, 800);
					} else {
						status.textContent = data.data && data.data.message ? data.data.message : __('Refresh failed', 'kalahamoon');
					}
				})
				.catch(function () {
					status.textContent = __('Network error', 'kalahamoon');
				})
				.finally(function () {
					btn.disabled = false;
					if (spinner) spinner.style.display = 'none';
				});
		});
	}

	function initCatalogSync() {
		document.querySelectorAll('[data-kalahamoon-catalog-sync]').forEach(function (button) {
			button.addEventListener('click', function () {
				if (button.disabled) return;
				button.disabled = true;
				button.setAttribute('aria-busy', 'true');
				fetch(window.ajaxurl + '?action=kalahamoon_sync_now&_wpnonce=' + encodeURIComponent(button.dataset.nonce || ''))
					.then(function (res) { return res.json(); })
					.then(function (data) {
						if (!data.success) throw new Error(data.data && data.data.message ? data.data.message : __('Sync failed', 'kalahamoon'));
						window.location.reload();
					})
					.catch(function (error) {
						button.disabled = false;
						button.removeAttribute('aria-busy');
						window.alert(error.message || __('Sync failed', 'kalahamoon'));
					});
			});
		});
	}

	/* ── Confirm before revoking affiliate links ── */
	function initRevokeConfirm() {
		document.querySelectorAll('.kalahamoon-revoke-link').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				var slug = btn.dataset.slug || __('this link', 'kalahamoon');
				var message = __('Are you sure you want to revoke the affiliate link "%s"? This cannot be undone.', 'kalahamoon')
					.replace('%s', slug);
				if (!window.confirm(message)) {
					e.preventDefault();
				}
			});
		});
	}

	function initLocalProductImagePicker() {
		var choose = document.getElementById('kalahamoon-select-product-image');
		var clear = document.getElementById('kalahamoon-clear-product-image');
		var imageId = document.getElementById('kalahamoon-product-image-id');
		var imageUrl = document.getElementById('kalahamoon-product-image-url');
		var preview = document.getElementById('kalahamoon-product-image-preview');
		if (!choose || !imageId || !imageUrl || !preview || !window.wp || !window.wp.media) return;

		choose.addEventListener('click', function () {
			var frame = window.wp.media({
				title: __('Select product image', 'kalahamoon'),
				button: { text: __('Use product image', 'kalahamoon') },
				multiple: false,
				library: { type: 'image' },
			});
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				imageId.value = attachment.id || '';
				imageUrl.value = attachment.url || '';
				preview.replaceChildren();
				var image = document.createElement('img');
				image.src = attachment.url || '';
				image.alt = '';
				preview.appendChild(image);
				preview.hidden = !attachment.url;
			});
			frame.open();
		});

		clear?.addEventListener('click', function () {
			imageId.value = '';
			imageUrl.value = '';
			preview.replaceChildren();
			preview.hidden = true;
		});
	}

	function initLocalProductDeleteConfirm() {
		document.querySelectorAll('.kalahamoon-delete-local-product').forEach(function (button) {
			button.addEventListener('click', function (event) {
				if (!window.confirm(__('Move this local product to the Trash?', 'kalahamoon'))) {
					event.preventDefault();
				}
			});
		});
	}

	/* ── Init on DOMContentLoaded ── */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
	initCopyProductId();
	initAnalyticsRefresh();
	initCatalogSync();
		initRevokeConfirm();
		initLocalProductImagePicker();
		initLocalProductDeleteConfirm();
	}
})();
