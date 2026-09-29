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

	function enhanceLookFilters() {
		const feature = document.querySelector('.ch-look-feature');
		const filters = document.querySelector('.ch-listing #ch-room-filters');
		const heading = feature?.querySelector('h1');
		if (!heading || !filters?.querySelector('a')) return;

		const quickFilters = filters.cloneNode(true);
		quickFilters.id = 'ch-room-filters-quick';
		quickFilters.classList.add('ch-room-filters--quick');
		quickFilters.setAttribute('aria-label', 'انتخاب فضای خانه');
		(heading.closest('.elementor-widget') || heading).after(quickFilters);
	}

	function onReady() {
		enhanceGalleryCounter();
		enhanceLookFilters();
		const header = document.querySelector('.ch-header');
		const searchQuery = window.chidemoonPublicDesign?.searchQuery || '';
		if (searchQuery) {
			document.querySelectorAll('.ch-header-search .e-search-input, .ch-page-search .e-search-input').forEach((input) => {
				if (!input.value) {
					input.value = searchQuery;
					input.defaultValue = searchQuery;
				}
			});
		}
		document.querySelectorAll('.ch-page-search .e-search-input').forEach((input) => {
			const search = input.closest('.ch-page-search');
			if (!input.id || !search || search.querySelector('.ch-page-search-label')) return;
			const label = Array.from(input.labels || []).find((candidate) => candidate.classList.contains('e-search-label')) || document.createElement('label');
			label.classList.remove('e-search-label');
			label.className = 'ch-page-search-label';
			label.htmlFor = input.id;
			label.textContent = 'جست‌وجو در محصولات و مطالب';
			search.prepend(label);
		});
		if (!header) return;

		const search = header.querySelector('.ch-header-search');
		const input = search?.querySelector('.e-search-input');
		const menuToggle = header.querySelector('.ch-nav .elementor-menu-toggle');
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
			if (open) input.focus({ preventScroll: true });
		});
		input.addEventListener('keydown', (event) => {
			if (event.key === 'Escape' && search.classList.contains('is-open')) {
				closeSearch();
				shortcut.focus();
			}
		});

		function syncHeader() {
			const mobile = window.matchMedia('(max-width: 767px)').matches;
			const scrolled = mobile && window.scrollY > 120;
			document.body.classList.toggle('ch-header-scrolled', scrolled);
			if (!scrolled) closeSearch();
			const menuOpen = mobile && !!menuToggle && (menuToggle.classList.contains('elementor-active') || menuToggle.getAttribute('aria-expanded') === 'true');
			document.body.classList.toggle('ch-mobile-menu-open', menuOpen);
			if (menuOpen) closeSearch();
		}
		window.addEventListener('scroll', syncHeader, { passive: true });
		window.addEventListener('resize', syncHeader, { passive: true });
		if (menuToggle) {
			new MutationObserver(syncHeader).observe(menuToggle, { attributes: true, attributeFilter: ['class', 'aria-expanded'] });
		}
		syncHeader();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', onReady, { once: true });
	} else {
		onReady();
	}
}());
