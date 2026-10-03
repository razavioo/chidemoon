(function () {
	'use strict';

	function enhanceGalleryCounter() {
		if (!document.body.classList.contains('single-product') && !document.querySelector('.ch-product-single')) return;
		const localize = (counter) => {
			const match = counter.textContent.trim().match(/^([0-9۰-۹٠-٩]+)\s*\/\s*([0-9۰-۹٠-٩]+)$/);
			if (!match) return;
			const persianDigits = (value) => value.replace(/[0-9٠-٩]/g, (digit) => {
				const code = digit.charCodeAt(0);
				return '۰۱۲۳۴۵۶۷۸۹'[code >= 1632 ? code - 1632 : code - 48];
			});
			counter.textContent = `تصویر ${persianDigits(match[1])} از ${persianDigits(match[2])}`;
		};
		const bind = (counter) => {
			counter.setAttribute('role', 'status');
			counter.setAttribute('aria-live', 'polite');
			counter.setAttribute('aria-atomic', 'true');
			new MutationObserver(() => localize(counter)).observe(counter, { childList: true, characterData: true, subtree: true });
			localize(counter);
		};
		const counter = document.querySelector('.pswp__counter');
		if (counter) {
			bind(counter);
			return;
		}
		const observer = new MutationObserver(() => {
			const addedCounter = document.querySelector('.pswp__counter');
			if (!addedCounter) return;
			observer.disconnect();
			bind(addedCounter);
		});
		observer.observe(document.body, { childList: true, subtree: true });
	}

	function enhanceHeader() {
		const header = document.querySelector('.ch-header');
		if (!header || header.querySelector('.ch-header-search-shortcut')) return;
		const searchQuery = window.chidemoonPublicDesign?.searchQuery || '';
		if (searchQuery) {
			document.querySelectorAll('.ch-header-search .e-search-input, .ch-page-search .e-search-input').forEach((input) => {
				if (!input.value) {
					input.value = searchQuery;
					input.defaultValue = searchQuery;
				}
			});
		}
		const search = header.querySelector('.ch-header-search');
		const input = search?.querySelector('.e-search-input');
		const menuToggle = header.querySelector('.ch-nav .e-n-menu-toggle, .ch-nav .elementor-menu-toggle');
		const row = header.querySelector('.e-con-inner') || header;
		if (!search || !input) return;
		if (!input.getAttribute('aria-label')) input.setAttribute('aria-label', 'جست‌وجو در چیدمون');

		const shortcut = document.createElement('button');
		shortcut.type = 'button';
		shortcut.className = 'ch-header-search-shortcut';
		shortcut.setAttribute('aria-label', 'نمایش جست‌وجو');
		shortcut.setAttribute('aria-expanded', 'false');
		shortcut.setAttribute('title', 'جست‌وجو');
		const icon = search.querySelector('.e-search-submit svg');
		if (icon) {
			const clone = icon.cloneNode(true);
			clone.setAttribute('aria-hidden', 'true');
			shortcut.appendChild(clone);
		} else {
			shortcut.textContent = 'جست‌وجو';
		}
		row.appendChild(shortcut);
		header.classList.add('ch-header-enhanced');

		function closeSearch() {
			search.classList.remove('is-open');
			shortcut.setAttribute('aria-expanded', 'false');
			shortcut.setAttribute('aria-label', 'نمایش جست‌وجو');
		}
		shortcut.addEventListener('click', () => {
			const open = !search.classList.contains('is-open');
			search.classList.toggle('is-open', open);
			shortcut.setAttribute('aria-expanded', String(open));
			shortcut.setAttribute('aria-label', open ? 'بستن جست‌وجو' : 'نمایش جست‌وجو');
			if (open) {
				if (menuToggle?.getAttribute('aria-expanded') === 'true') menuToggle.click();
				input.focus({ preventScroll: true });
			}
		});
		input.addEventListener('keydown', (event) => {
			if (event.key === 'Escape' && search.classList.contains('is-open')) {
				closeSearch();
				shortcut.focus();
			}
		});
		document.addEventListener('click', (event) => {
			if (search.classList.contains('is-open') && !search.contains(event.target) && !shortcut.contains(event.target)) closeSearch();
		});

		function syncHeader() {
			const mobile = window.matchMedia('(max-width: 1024px)').matches;
			if (!mobile) closeSearch();
			const collapsedMenu = !!menuToggle && getComputedStyle(menuToggle).display !== 'none';
			const menuOpen = collapsedMenu && (menuToggle.classList.contains('elementor-active') || menuToggle.getAttribute('aria-expanded') === 'true');
			document.body.classList.toggle('ch-mobile-menu-open', menuOpen);
			if (menuOpen) closeSearch();
		}
		window.addEventListener('resize', syncHeader, { passive: true });
		if (menuToggle) {
			new MutationObserver(syncHeader).observe(menuToggle, { attributes: true, attributeFilter: ['class', 'aria-expanded'] });
		}
		syncHeader();
	}

	function onReady() {
		enhanceGalleryCounter();
		const adminBar = document.getElementById('wpadminbar');
		if (adminBar) {
			const syncAdminOffset = () => document.documentElement.style.setProperty('--ch-admin-bar-offset', `${Math.max(0, adminBar.getBoundingClientRect().bottom)}px`);
			window.addEventListener('scroll', syncAdminOffset, { passive: true });
			window.addEventListener('resize', syncAdminOffset, { passive: true });
			syncAdminOffset();
		}

		enhanceHeader();
		// Elementor replaces widget markup while editing; enhance each new header once.
		new MutationObserver(enhanceHeader).observe(document.body, { childList: true, subtree: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', onReady, { once: true });
	} else {
		onReady();
	}
}());
