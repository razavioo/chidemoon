(function () {
	'use strict';

	function showProductSkeleton(widget, append = false) {
		const grid = widget?.querySelector('.elementor-loop-container, ul.products');
		if (!grid) return () => {};
		widget.querySelectorAll('.ch-catalog-skeleton').forEach(node => node.remove());
		const skeleton = document.createElement('div');
		skeleton.className = append ? 'ch-catalog-skeleton ch-catalog-skeleton--more' : 'elementor-loop-container ch-catalog-skeleton';
		skeleton.setAttribute('aria-hidden', 'true');
		const columns = getComputedStyle(grid).gridTemplateColumns.split(' ').length;
		const count = append ? columns : Math.max(columns, Math.min(grid.children.length || columns * 2, columns * 2));
		for (let i = 0; i < count; i++) {
			const card = document.createElement('div'); card.className = 'ch-skeleton-card';
			card.innerHTML = '<div class="ch-skeleton-photo"></div><div class="ch-skeleton-body"><div class="ch-skeleton-line"></div><div class="ch-skeleton-line ch-skeleton-line--short"></div><div class="ch-skeleton-price"></div><div class="ch-skeleton-action"></div></div>';
			skeleton.append(card);
		}
		if (append) grid.append(skeleton);
		else grid.after(skeleton);
		return () => skeleton.remove();
	}

	function enhancePriceSteps(form) {
		const min = form.querySelector('[name="ch_min_price"]');
		const max = form.querySelector('[name="ch_max_price"]');
		const format = value => Number(value).toLocaleString('fa-IR');
		const controls = [];
		[min, max].filter(Boolean).forEach(input => {
			if (input.closest('.ch-price-stepper')) return;
			input.step = 'any';
			input.id ||= `ch-price-${input.name}-${document.querySelectorAll('.ch-price-stepper').length}`;
			const label = input.closest('label');
			if (label) label.htmlFor = input.id;
			const wrapper = document.createElement('span'); wrapper.className = 'ch-price-stepper';
			const minus = document.createElement('button'); minus.type = 'button'; minus.textContent = '−';
			const plus = document.createElement('button'); plus.type = 'button'; plus.textContent = '+';
			const hint = document.createElement('span'); hint.className = 'ch-price-hint';
			hint.setAttribute('aria-live', 'polite');
			input.before(wrapper); wrapper.append(minus, input, plus); wrapper.after(hint);
			if (label) {
				const heading = document.createElement('span'); heading.className = 'ch-price-label';
				const title = document.createElement('span');
				[...label.childNodes].filter(node => node.nodeType === Node.TEXT_NODE).forEach(node => title.append(node));
				heading.append(title, hint); label.prepend(heading);
			}
			const next = direction => {
				const value = Math.max(0, Number(input.value) || (input === max ? Number(min?.value) : 0) || 0);
				const basis = direction < 0 ? Math.max(0, value - 1) : value;
				const step = basis < 1000000 ? 100000 : basis < 10000000 ? 1000000 : 10000000;
				let result = direction > 0 ? (Math.floor(value / step) + 1) * step : (Math.ceil(value / step) - 1) * step;
				result = Math.max(0, result);
				if (input === min && max && max.value !== '') result = Math.min(result, Number(max.value));
				if (input === max && min && min.value !== '') result = Math.max(result, Number(min.value));
				return result;
			};
			const sync = () => {
				const value = Number(input.value) || 0;
				minus.disabled = next(-1) === value;
				plus.disabled = next(1) === value;
				minus.setAttribute('aria-label', `کاهش ${input === min ? 'حداقل' : 'حداکثر'} قیمت به ${format(next(-1))} تومان`);
				plus.setAttribute('aria-label', `افزایش ${input === min ? 'حداقل' : 'حداکثر'} قیمت به ${format(next(1))} تومان`);
				minus.title = minus.getAttribute('aria-label'); plus.title = plus.getAttribute('aria-label');
				hint.textContent = input.value ? `${format(value)} تومان` : '';
			};
			const change = direction => {
				input.value = String(next(direction));
				input.dispatchEvent(new Event('input', { bubbles: true }));
				input.dispatchEvent(new Event('change', { bubbles: true }));
			};
			minus.addEventListener('click', () => change(-1)); plus.addEventListener('click', () => change(1));
			input.addEventListener('keydown', event => {
				if (event.key === 'ArrowUp' || event.key === 'ArrowDown') { event.preventDefault(); change(event.key === 'ArrowUp' ? 1 : -1); }
			});
			controls.push(sync);
		});
		const syncAll = () => controls.forEach(sync => sync());
		form.addEventListener('input', syncAll); form.addEventListener('change', syncAll); syncAll();
	}

	function enhanceCatalogSorting() {
		document.querySelectorAll('.woocommerce-ordering select.orderby').forEach((select, index) => {
			if (select.dataset.chEnhanced) return;
			select.dataset.chEnhanced = 'true';
			const wrapper = document.createElement('div');
			wrapper.className = 'ch-sort-select';
			const trigger = document.createElement('button');
			trigger.type = 'button'; trigger.className = 'ch-sort-trigger';
			trigger.id = `ch-sort-trigger-${index}`;
			trigger.setAttribute('aria-haspopup', 'menu');
			trigger.setAttribute('aria-expanded', 'false');
			const menu = document.createElement('div');
			menu.className = 'ch-sort-menu'; menu.id = `ch-sort-menu-${index}`;
			menu.setAttribute('role', 'menu'); menu.setAttribute('aria-labelledby', trigger.id); menu.hidden = true;
			trigger.setAttribute('aria-controls', menu.id);
			const label = select.form.querySelector(`label[for="${select.id}"]`);
			if (label) label.htmlFor = trigger.id;
			const results = select.closest('.ch-catalog-results');
			let request, committedValue = select.value;
			const status = document.createElement('p');
			status.className = 'ch-sort-status'; status.setAttribute('role', 'status'); status.hidden = true;
			if (results) select.form.closest('.ch-catalog-tools').after(status);
			const updateResults = async (url, push = true) => {
				request?.abort();
				const controller = new AbortController(); request = controller;
				results.setAttribute('aria-busy', 'true');
				const clearSkeleton = showProductSkeleton(results.querySelector('.elementor-widget-loop-grid'));
				status.hidden = true; status.textContent = '';
				try {
					const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin' });
					if (!response.ok) throw new Error('catalog');
					const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
					const remoteResults = doc.querySelector('.ch-catalog-results');
					const current = results.querySelector('.elementor-widget-loop-grid');
					const remote = remoteResults?.querySelector('.elementor-widget-loop-grid');
					if (!current || !remote) throw new Error('listing');
					if (controller.signal.aborted) return;
					const replacement = document.importNode(remote, true);
					current.replaceWith(replacement);
					select.value = remoteResults.querySelector('select.orderby')?.value || select.value;
					committedValue = select.value; sync();
					if (push) history.pushState(null, '', url);
					document.querySelectorAll('.ch-sidebar-filters input[name="orderby"], .ch-catalog-filters input[name="orderby"]').forEach(input => { input.value = select.value; });
					enhanceInfiniteListings(results);
					window.elementorFrontend?.elementsHandler?.runReadyTrigger?.(window.jQuery?.(replacement));
				} catch (error) {
					if (error.name === 'AbortError') return;
					select.value = committedValue; sync();
					status.hidden = false;
					status.textContent = 'بارگذاری انجام نشد؛ لطفاً دوباره گزینهٔ مرتب‌سازی را انتخاب کنید.';
				} finally {
					clearSkeleton();
					if (request === controller) results.removeAttribute('aria-busy');
				}
			};
			const sortResults = () => {
				const url = new URL(location.href);
				url.pathname = url.pathname.replace(/\/page\/\d+\/?$/, '/');
				for (const key of [...url.searchParams.keys()]) {
					if (key === 'paged' || key === 'product-page' || key.startsWith('e-page-')) url.searchParams.delete(key);
				}
				url.searchParams.set('orderby', select.value);
				updateResults(url);
			};
			if (results) {
				select.form.addEventListener('submit', event => { event.preventDefault(); sortResults(); });
				select.addEventListener('change', event => { event.stopImmediatePropagation(); sortResults(); }, true);
				results.addEventListener('click', event => {
					const link = event.target.closest('.elementor-pagination a');
					if (!link || event.button || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
					event.preventDefault(); event.stopPropagation(); updateResults(new URL(link.href));
				}, true);
				window.addEventListener('popstate', () => updateResults(new URL(location.href), false));
			}
			const buttons = [...select.options].map(option => {
				const button = document.createElement('button');
				button.type = 'button'; button.className = 'ch-sort-option';
				button.textContent = option.textContent; button.disabled = option.disabled;
				button.setAttribute('role', 'menuitemradio'); button.tabIndex = -1;
				button.addEventListener('click', () => {
					select.value = option.value; sync(); close(true);
					if (results) sortResults();
					else if (window.jQuery) window.jQuery(select).trigger('change');
					else select.dispatchEvent(new Event('change', { bubbles: true }));
				});
				menu.append(button); return button;
			});
			const sync = () => {
				trigger.textContent = select.selectedOptions[0]?.textContent || '';
				trigger.disabled = select.disabled;
				buttons.forEach((button, i) => button.setAttribute('aria-checked', String(select.options[i].selected)));
			};
			const close = (restoreFocus = false) => {
				menu.hidden = true; trigger.setAttribute('aria-expanded', 'false');
				if (restoreFocus) trigger.focus();
			};
			const open = () => {
				menu.hidden = false; trigger.setAttribute('aria-expanded', 'true');
				(buttons[select.selectedIndex] || buttons[0])?.focus();
			};
			trigger.addEventListener('click', () => menu.hidden ? open() : close());
			wrapper.addEventListener('keydown', event => {
				if (event.key === 'Escape') { event.preventDefault(); close(true); }
				if (event.key === 'Tab') close();
				if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
					event.preventDefault();
					if (menu.hidden) { open(); return; }
					const available = buttons.filter(button => !button.disabled);
					const current = available.indexOf(document.activeElement);
					const next = event.key === 'Home' ? 0 : event.key === 'End' ? available.length - 1 : (current + (event.key === 'ArrowDown' ? 1 : -1) + available.length) % available.length;
					available[next]?.focus();
				}
			});
			document.addEventListener('pointerdown', event => { if (!wrapper.contains(event.target)) close(); });
			wrapper.addEventListener('focusout', event => { if (!wrapper.contains(event.relatedTarget)) close(); });
			select.addEventListener('change', sync);
			wrapper.append(trigger, menu); select.after(wrapper); select.hidden = true; sync();
		});
	}

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

	function syncHeaderNavigation(header) {
		// Elementor may cache a header rendered on another page, including e-current.
		const path = window.location.pathname.replace(/\/+$/, '') || '/';
		header.querySelectorAll('.ch-mega-nav .e-n-menu-title-container[href]').forEach((link) => {
			const url = new URL(link.href, window.location.href);
			const target = url.pathname.replace(/\/+$/, '') || '/';
			const exact = path === target;
			const productSection = target === '/shop' && (document.body.classList.contains('single-product') || document.body.classList.contains('tax-product_cat'));
			const active = url.origin === window.location.origin && (exact || (target !== '/' && path.startsWith(`${target}/`)) || productSection);
			link.closest('.e-n-menu-title')?.classList.toggle('e-current', active);
			if (active) link.setAttribute('aria-current', exact ? 'page' : 'location');
			else link.removeAttribute('aria-current');
		});
	}

	function enhanceHeader() {
		const header = document.querySelector('.ch-header');
		if (header) syncHeaderNavigation(header);
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
		shortcut.setAttribute('aria-label', 'جستجو در چیدمون');
		shortcut.setAttribute('aria-expanded', 'false');
		shortcut.setAttribute('aria-haspopup', 'dialog');
		shortcut.setAttribute('aria-controls', 'ch-search-dialog');
		shortcut.title = 'جستجو';
		shortcut.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.5 4.5"/></svg>';
		row.appendChild(shortcut);
		const dialog = document.createElement('dialog');
		dialog.id = 'ch-search-dialog';
		dialog.className = 'ch-search-dialog';
		const elementorRoot = search.closest('.elementor');
		if (elementorRoot) dialog.classList.add(...Array.from(elementorRoot.classList).filter(name => name === 'elementor' || /^elementor-\d+$/.test(name)));
		dialog.dir = 'rtl';
		dialog.tabIndex = -1;
		dialog.setAttribute('aria-labelledby', 'ch-search-title');
		dialog.setAttribute('aria-describedby', 'ch-search-description');
		dialog.innerHTML = '<div class="ch-search-dialog-heading"><div><span class="ch-search-eyebrow">خانه‌ای به سلیقهٔ تو</span><h2 id="ch-search-title">دنبال چی می‌گردی؟</h2></div><button type="button" class="ch-search-close" aria-label="بستن جستجو"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg></button></div><p id="ch-search-description">از محصولات تا ایده‌های چیدمان؛ اینجا پیدایش کن.</p><div class="ch-search-dialog-body"></div><p class="ch-search-hint">نام محصول، سبک یا موضوع موردنظرت را بنویس.</p>';
		document.body.appendChild(dialog);
		input.placeholder = 'جستجو در چیدمون';
		input.setAttribute('enterkeyhint', 'search');
		input.addEventListener('keydown', event => {
			if (event.key === 'Enter' && !event.isComposing) {
				event.preventDefault();
				event.stopPropagation();
				input.form?.requestSubmit();
			}
		});
		header.classList.add('ch-header-enhanced');
		const menuPanel = header.querySelector('.e-n-menu-wrapper');
		const mobileMenu = window.matchMedia('(max-width: 1024px)');
		const menuHeading = menuPanel?.querySelector('.e-n-menu-heading');
		const originalItems = menuHeading ? [...menuHeading.children] : [];
		const homeItem = originalItems.find(item => {
			const link = item.querySelector('.e-n-menu-title-container[href]');
			return link && new URL(link.href).pathname === '/';
		});
		// Elementor's hover setting also applies to mouse-driven narrow windows.
		['mouseover', 'mouseenter', 'mouseout', 'mouseleave', 'pointerover', 'pointerenter', 'pointerout', 'pointerleave'].forEach(type => {
			header.addEventListener(type, event => {
				if (mobileMenu.matches && event.target.closest('.e-n-menu-item')) event.stopImmediatePropagation();
			}, true);
		});
		header.addEventListener('click', event => {
			if (mobileMenu.matches) {
				const title = event.target.closest('.e-n-menu-title');
				const toggle = title?.querySelector('.e-n-menu-dropdown-icon');
				if (toggle) {
					event.preventDefault();
					event.stopImmediatePropagation();
					const item = title.closest('.e-n-menu-item');
					const open = item.classList.toggle('ch-mobile-expanded');
					toggle.setAttribute('aria-expanded', String(open));
					return;
				}
			}
			const title = event.target.closest('.e-n-menu-title-container[href]');
			const toggle = title?.closest('.e-n-menu-title')?.querySelector('.e-n-menu-dropdown-icon');
			if (!toggle) return;
			event.preventDefault();
			event.stopImmediatePropagation();
			if (toggle.getAttribute('aria-expanded') !== 'true') toggle.click();
		}, true);
		const searchHome = document.createComment('header-search');
		search.before(searchHome);
		let drawerHeading;
		if (menuPanel && menuToggle) {
			menuToggle.setAttribute('aria-label', 'باز کردن منو');
			drawerHeading = document.createElement('div');
			drawerHeading.className = 'ch-drawer-heading';
			drawerHeading.innerHTML = '<div><strong>چیدمون</strong><span>خانه، به سلیقهٔ تو</span></div>';
			drawerHeading.tabIndex = -1;
			menuPanel.prepend(drawerHeading);
			const backdrop = document.createElement('button');
			backdrop.className = 'ch-drawer-backdrop';
			backdrop.type = 'button';
			backdrop.tabIndex = -1;
			backdrop.setAttribute('aria-label', 'بستن منو');
			header.appendChild(backdrop);
			const closeMenu = () => {
				if (menuToggle.getAttribute('aria-expanded') === 'true') menuToggle.click();
				menuToggle.focus({ preventScroll: true });
			};
			backdrop.addEventListener('click', closeMenu);
			document.addEventListener('keydown', event => {
				if (!document.body.classList.contains('ch-mobile-menu-open')) return;
				if (event.key === 'Escape') { event.preventDefault(); closeMenu(); }
				if (event.key === 'Tab') {
					const targets = Array.from(menuPanel.querySelectorAll('a[href], button, input')).filter(el => el.getClientRects().length && !el.disabled && el.tabIndex >= 0);
					const first = targets[0], last = targets[targets.length - 1];
					if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
					else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
				}
			});
		}
		function closeSearch() { if (dialog.open) dialog.close(); }
		dialog.addEventListener('keydown', event => {
			if (event.key === 'Escape') {
				event.preventDefault();
				event.stopPropagation();
				closeSearch();
			}
		}, true);
		dialog.addEventListener('close', () => {
			document.documentElement.classList.remove('ch-search-modal-open');
			shortcut.setAttribute('aria-expanded', 'false');
			shortcut.focus({ preventScroll: true });
		});
		dialog.querySelector('.ch-search-close').addEventListener('click', closeSearch);
		const outside = event => {
			const r = dialog.getBoundingClientRect();
			return event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom;
		};
		let backdropPress = false;
		dialog.addEventListener('pointerdown', event => { backdropPress = outside(event); });
		dialog.addEventListener('click', event => { if (backdropPress && outside(event)) closeSearch(); });
		shortcut.addEventListener('click', () => {
			if (dialog.open) return;
			if (menuToggle?.getAttribute('aria-expanded') === 'true') menuToggle.click();
			dialog.querySelector('.ch-search-dialog-body').appendChild(search);
			search.classList.add('is-open');
			dialog.showModal();
			document.documentElement.classList.add('ch-search-modal-open');
			shortcut.setAttribute('aria-expanded', 'true');
			dialog.focus({ preventScroll: true });
		});

		function syncHeader() {
			const mobile = window.matchMedia('(max-width: 1024px)').matches;
			if (menuHeading && homeItem) {
				if (mobile && menuHeading.firstElementChild !== homeItem) menuHeading.prepend(homeItem);
				else if (!mobile && menuHeading.firstElementChild !== originalItems[0]) originalItems.forEach(item => menuHeading.append(item));
			}
			if (menuPanel && drawerHeading) {
				if (mobile && search.parentNode !== menuPanel) drawerHeading.after(search);
				else if (!mobile && search.parentNode === menuPanel) searchHome.after(search);
			}
			if (!mobile) closeSearch();
			const collapsedMenu = !!menuToggle && getComputedStyle(menuToggle).display !== 'none';
			const menuOpen = collapsedMenu && (menuToggle.classList.contains('elementor-active') || menuToggle.getAttribute('aria-expanded') === 'true');
			const wasOpen = document.body.classList.contains('ch-mobile-menu-open');
			document.body.classList.toggle('ch-mobile-menu-open', mobile && menuOpen);
			if (mobile && menuOpen && !wasOpen) drawerHeading?.focus({ preventScroll: true });
			if (menuOpen) closeSearch();
		}
		window.addEventListener('resize', syncHeader, { passive: true });
		if (menuToggle) {
			new MutationObserver(syncHeader).observe(menuToggle, { attributes: true, attributeFilter: ['class', 'aria-expanded'] });
		}
		syncHeader();
	}

	function enhanceSceneHotspots() {
		document.querySelectorAll('.ch-scene-canvas:not([data-ready])').forEach((canvas) => {
			canvas.dataset.ready = 'true';
			let active, timer;
			const close = () => {
				clearTimeout(timer);
				canvas.querySelectorAll('.ch-scene-popover').forEach(p => { p.hidden = true; });
				canvas.querySelectorAll('.ch-scene-point').forEach(b => b.setAttribute('aria-expanded', 'false'));
				active = null;
			};
			const position = () => {
				if (!active) return;
				const panel = document.getElementById(active.getAttribute('aria-controls'));
				const c = canvas.getBoundingClientRect(), b = active.getBoundingClientRect();
				const x = b.left - c.left + b.width / 2;
				let y = b.bottom - c.top + 10;
				const above = b.top - c.top - panel.offsetHeight - 10;
				if (y + panel.offsetHeight > c.height && above >= 8) y = above;
				panel.style.left = `${Math.max(12, Math.min(x - panel.offsetWidth / 2, c.width - panel.offsetWidth - 12))}px`;
				panel.style.top = `${Math.max(8, y)}px`;
			};
			const open = (button) => {
				close(); active = button;
				document.getElementById(button.getAttribute('aria-controls')).hidden = false;
				button.setAttribute('aria-expanded', 'true'); position();
			};
			canvas.querySelectorAll('.ch-scene-point').forEach(button => {
				button.addEventListener('pointerenter', e => { if (e.pointerType === 'mouse') open(button); });
				button.addEventListener('focus', () => open(button));
				button.addEventListener('click', () => open(button));
			});
			canvas.addEventListener('pointerover', () => clearTimeout(timer));
			canvas.addEventListener('pointerleave', () => { timer = setTimeout(close, 160); });
			canvas.addEventListener('focusout', e => { if (!canvas.contains(e.relatedTarget)) close(); });
			canvas.addEventListener('keydown', e => {
				if (e.key === 'Escape') { const button = active; close(); if (button) { button.focus(); close(); } }
			});
			document.addEventListener('click', e => { if (!canvas.contains(e.target)) close(); });
			window.addEventListener('resize', position, { passive: true });
		});
	}

	function onReady() {
		enhanceStorefront();
		enhanceSceneHotspots();
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
		new MutationObserver(() => { enhanceHeader(); enhanceSceneHotspots(); }).observe(document.body, { childList: true, subtree: true });
	}

	function enhanceInfiniteListings(root = document) {
		// Keep normal pagination as a no-JS/error fallback. Each listing owns its next URL.
		if ('IntersectionObserver' in window) root.querySelectorAll('.elementor-widget-loop-grid, .elementor-widget-woocommerce-products, .elementor-widget-woocommerce-archive-products, .ch-editorial-feed, .ch-chooser-results').forEach(widget => {
			const grid = widget.querySelector('.elementor-loop-container, ul.products, .elementor-posts-container, .ch-chooser-list');
			const pagination = widget.querySelector('.elementor-pagination, .woocommerce-pagination, .ch-chooser-pagination');
			if (!grid || !pagination || widget.dataset.chInfinite) return;
			widget.dataset.chInfinite = 'true';
			const nextPage = nav => nav?.querySelector('a.next')?.href || nav?.querySelector('[aria-current="page"]')?.nextElementSibling?.closest('a')?.href;
			let next = nextPage(pagination), busy = false;
			if (!next) return;
			const seen = new Set();
			const status = document.createElement('button'); status.type = 'button'; status.className = 'ch-scroll-status'; status.textContent = 'نمایش موارد بیشتر';
			status.setAttribute('aria-live', 'polite'); pagination.after(status); pagination.hidden = true;
			const load = async () => {
				if (!widget.isConnected) { observer.disconnect(); return; }
				if (busy || !next || seen.has(next)) return;
				busy = true; status.textContent = 'در حال نمایش موارد بیشتر…'; grid.setAttribute('aria-busy', 'true');
				const clearSkeleton = widget.closest('.ch-catalog-results') ? showProductSkeleton(widget, true) : () => {};
				try {
					const url = next, response = await fetch(url);
					if (!response.ok) throw new Error('pagination');
					const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
					const id = widget.dataset.id;
					const remote = id ? doc.querySelector(`[data-id="${id}"]`) : null;
					const source = remote?.querySelector('.elementor-loop-container, ul.products, .elementor-posts-container, .ch-chooser-list');
					if (!source?.children.length) throw new Error('listing');
					[...source.children].forEach(child => grid.append(document.importNode(child, true)));
					seen.add(url); next = nextPage(remote.querySelector('.elementor-pagination, .woocommerce-pagination, .ch-chooser-pagination'));
					if (next) status.textContent = 'نمایش موارد بیشتر';
					else { observer.disconnect(); status.remove(); }
					window.elementorFrontend?.elementsHandler?.runReadyTrigger?.(window.jQuery?.(grid));
				} catch { observer.disconnect(); pagination.hidden = false; status.textContent = 'بارگذاری انجام نشد؛ تلاش دوباره'; }
				finally { clearSkeleton(); busy = false; grid.removeAttribute('aria-busy'); if (next && pagination.hidden) { observer.unobserve(status); observer.observe(status); } }
			};
			status.addEventListener('click', load);
			const observer = new IntersectionObserver(entries => { if (entries.some(e => e.isIntersecting)) load(); }, { rootMargin: '350px' }); observer.observe(status);
		});
	}

	function enhanceStorefront() {
		document.querySelectorAll('.ch-sidebar-filters, .ch-catalog-filters').forEach((form, index) => {
			enhancePriceSteps(form);
			const state = () => JSON.stringify([...new FormData(form)].filter(([key]) => key.startsWith('ch_')).sort());
			const initial = state();
			const submit = form.querySelector('button[type="submit"]');
			const min = form.querySelector('[name="ch_min_price"]');
			const max = form.querySelector('[name="ch_max_price"]');
			const error = document.createElement('p'); error.className = 'ch-filter-error'; error.hidden = true; error.id = `ch-filter-error-${index}`; error.setAttribute('role', 'alert');
			if (max) { max.closest('fieldset')?.append(error); max.setAttribute('aria-describedby', [max.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' ')); }
			const sync = () => {
				const reversed = !!(min?.value && max?.value && Number(min.value) > Number(max.value));
				error.hidden = !reversed; error.textContent = reversed ? 'حداکثر قیمت باید بیشتر از حداقل قیمت باشد.' : '';
				if (max) { max.setCustomValidity(error.textContent); max.setAttribute('aria-invalid', String(reversed)); }
				if (submit) submit.disabled = state() === initial || !form.checkValidity();
			};
			form.addEventListener('input', sync); form.addEventListener('change', sync);
			form.addEventListener('submit', event => { if (state() === initial) event.preventDefault(); });
			const reset = form.querySelector('.ch-sidebar-reset');
			const applied = [...new FormData(form)].filter(([key, value]) => key.startsWith('ch_') && value !== '').length;
			if (reset) reset.hidden = !applied;
			if (form.classList.contains('ch-sidebar-filters')) {
				const heading = document.createElement('div'); heading.className = 'ch-filter-heading';
				const title = document.createElement('strong'); title.textContent = 'فیلتر محصولات';
				const icon = document.createElement('span'); icon.className = 'ch-filter-heading-icon'; icon.setAttribute('aria-hidden', 'true');
				icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3" fill="white"/><circle cx="15" cy="17" r="3" fill="white"/></svg>';
				heading.append(icon, title); form.prepend(heading);
				const toggle = document.createElement('button');
				toggle.type = 'button'; toggle.className = 'ch-filter-toggle'; toggle.textContent = 'فیلتر محصولات' + (applied ? ` (${applied.toLocaleString('fa-IR')})` : '');
				form.id ||= `ch-catalog-filter-form-${index}`;
				toggle.setAttribute('aria-controls', form.id); toggle.setAttribute('aria-expanded', 'false');
				form.before(toggle);
				const setOpen = open => { toggle.setAttribute('aria-expanded', String(open)); form.classList.toggle('is-open', open); };
				toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
				form.addEventListener('keydown', event => { if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') { setOpen(false); toggle.focus(); } });
			}
			sync();
		});
		enhanceInfiniteListings();
		const article = document.querySelector('.single-post .ch-article-body');
		if (article && !document.querySelector('.ch-guide-toc')) {
			const headings = [...article.querySelectorAll('h2, h3')].filter(h => h.textContent.trim());
			if (headings.length > 1) {
				const layout = document.createElement('div'); layout.className = 'ch-reading-layout'; article.before(layout); layout.append(article);
				const toc = document.createElement('nav'); toc.className = 'ch-guide-toc'; toc.setAttribute('aria-label', 'فهرست مطلب');
				const title = document.createElement('strong'); title.textContent = 'فهرست مطلب'; toc.append(title);
				headings.forEach((h, index) => { if (!h.id) h.id = `guide-section-${index + 1}`; const a = document.createElement('a'); a.href = `#${h.id}`; a.textContent = h.textContent; a.classList.toggle('is-subsection', h.tagName === 'H3'); toc.append(a); });
				layout.append(toc);
				const sync = () => { const current = headings.filter(h => h.getBoundingClientRect().top <= 180).at(-1) || headings[0]; toc.querySelectorAll('a').forEach(a => { if (a.hash === `#${current.id}`) a.setAttribute('aria-current', 'location'); else a.removeAttribute('aria-current'); }); };
				window.addEventListener('scroll', sync, { passive: true }); sync();
			}
		}
		const searchIcon = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg>';
		document.querySelectorAll('.ch-header-search .e-search-submit').forEach(button => { button.innerHTML = searchIcon; button.setAttribute('aria-label', 'جستجو در چیدمون'); });
		const shapes = [
			[/چراغ|نور|روشن/, '<path d="m65 30-22 45h74L95 30ZM80 75v54m-25 0h50"/><path d="m48 88-8 13m72-13 8 13"/>'],
			[/خواب|تخت|کالای خواب/, '<path d="M25 123V65q0-12 12-12h86q12 0 12 12v58M25 88h110M35 88V70h35v18m20 0V70h35v18M25 113h110"/>'],
			[/آشپز|پذیرایی|ظرف|قاشق/, '<circle cx="85" cy="83" r="36"/><circle cx="85" cy="83" r="25"/><path d="M30 42v30m-7-30v21q0 9 14 9V42m-7 30v55m101-85v85m0-85q-20 17 0 40"/>'],
			[/فرش|بافت|نساج|منسوج|پرده/, '<path d="M36 38h88v90H36zM46 48h68v70H46zM36 32v-8m22 8v-8m22 8v-8m22 8v-8m22 8v-8M36 134v8m22-8v8m22-8v8m22-8v8m22-8v8M80 63l20 20-20 20-20-20Z"/>'],
			[/دکور|گلدان|گیاه/, '<path d="M58 90h44l-8 43H66ZM80 90V45m0 23q-40 0-28-33 29 0 28 33Zm0-10q0-30 30-33 5 30-30 33Z"/>'],
			[/مبل|نشیمن|صندلی/, '<path d="M35 82V58q0-15 15-15h60q15 0 15 15v24M35 76q-17-8-17 10v25h124V86q0-18-17-10v22H35ZM30 111v17m100-17v17"/>'],
			[/آینه/, '<path d="M48 125V62a32 32 0 0 1 64 0v63ZM57 115V62a23 23 0 0 1 46 0v53ZM65 70l24-24m-20 48 26-26"/>'],
			[/میز/, '<path d="M25 64h110v17H25ZM39 81v51m82-51v51M56 64V40h48v24"/>'],
			[/قفسه|کتاب|نظم|نگهدار|کمد/, '<path d="M35 28h90v108H35ZM35 64h90M35 100h90M48 64V40h14v24m8 0V35h16v29m12 0 10-26 13 5-8 21M50 100V77h50v23M45 136v9m70-9v9"/>'],
			[/تابلو|دیوار/, '<path d="M30 35h100v95H30ZM40 45h80v75H40ZM40 105l25-26 20 20 15-16 20 22"/><circle cx="93" cy="65" r="10"/>'],
			[/حمام|سرویس|بهداشت/, '<path d="M25 80h110l-12 39H42ZM42 119v12m76-12v12M40 80V45q0-18 20-18h10v14H52v39"/>'],
			[/./, '<path d="m30 77 50-42 50 42v53H30ZM60 130V89h40v41M38 60V35h17v11"/>']
		];
		document.querySelectorAll('.ch-category-tree').forEach((tree, treeIndex) => {
			const roots = [...tree.querySelectorAll(':scope > ul > li')];
			if (!roots.length) return;
			const panel = document.createElement('div'); panel.className = 'ch-category-detail';
			tree.append(panel); tree.classList.add('ch-category-browser');
			const show = item => {
				const link = item.querySelector(':scope > a'); if (!link) return;
				roots.forEach(root => root.classList.toggle('is-active', root === item));
				panel.replaceChildren();
				const all = document.createElement('a'); all.href = link.href; all.className = 'ch-category-view-all'; all.textContent = `همه محصولات ${link.textContent.trim()}`; panel.append(all);
				const children = item.querySelector(':scope > ul');
				if (children) panel.append(children.cloneNode(true));
				else {
					const id = item.className.match(/(?:^|\s)cat-item-(\d+)(?:\s|$)/)?.[1];
					const samples = id && tree.querySelector(`template[data-category-samples="${id}"]`);
					if (samples) panel.append(samples.content.cloneNode(true));
				}
				const preview = document.createElement('div'); preview.className = 'ch-category-preview'; preview.setAttribute('aria-hidden', 'true');
				preview.innerHTML = `<svg viewBox="0 0 160 160" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round">${shapes.find(([pattern]) => pattern.test(link.textContent))[1]}</svg>`;
				panel.append(preview);
			};
			roots.forEach((item, index) => {
				const link = item.querySelector(':scope > a'); if (!link) return;
				const button = document.createElement('button');
				button.type = 'button'; button.className = 'ch-category-toggle';
				const icon = document.createElement('span'); icon.className = 'ch-category-icon'; icon.setAttribute('aria-hidden', 'true');
				icon.innerHTML = `<svg viewBox="0 0 160 160" fill="none" stroke="currentColor" stroke-width="5" stroke-linejoin="round" stroke-linecap="round">${shapes.find(([pattern]) => pattern.test(link.textContent))[1]}</svg>`;
				const label = document.createElement('span'); label.textContent = link.textContent;
				button.append(icon, label); link.hidden = true; link.after(button);
				const section = document.createElement('div'); section.className = 'ch-category-accordion'; section.id = `ch-category-${treeIndex}-${index}`;
				const all = document.createElement('a'); all.href = link.href; all.className = 'ch-category-view-all'; all.textContent = `همهٔ محصولات ${link.textContent.trim()}`;
				section.append(all);
				const children = item.querySelector(':scope > ul');
				if (children) section.append(children.cloneNode(true));
				item.append(section); button.setAttribute('aria-controls', section.id); button.setAttribute('aria-expanded', 'false');
				button.addEventListener('click', () => {
					const open = button.getAttribute('aria-expanded') !== 'true';
					roots.forEach(root => { root.classList.remove('is-open'); root.querySelector('.ch-category-toggle')?.setAttribute('aria-expanded', 'false'); });
					item.classList.toggle('is-open', open); button.setAttribute('aria-expanded', String(open)); show(item);
				});
				button.addEventListener('pointerenter', event => { if (event.pointerType === 'mouse') show(item); });
				button.addEventListener('focus', () => show(item));
				button.addEventListener('keydown', event => { if (event.key === 'ArrowLeft' && getComputedStyle(panel).display !== 'none') { event.preventDefault(); panel.querySelector('a')?.focus(); } });
				link.addEventListener('pointerenter', event => { if (event.pointerType === 'mouse') show(item); });
				link.addEventListener('focus', () => show(item));
				link.addEventListener('keydown', event => { if (event.key === 'ArrowLeft' && getComputedStyle(panel).display !== 'none') { event.preventDefault(); panel.querySelector('a')?.focus(); } });
			});
			panel.addEventListener('keydown', event => { if (event.key === 'ArrowRight' || event.key === 'Escape') { event.preventDefault(); tree.querySelector(':scope > ul > li.is-active > .ch-category-toggle')?.focus(); } });
			show(roots[0]);
		});
		const product = document.querySelector('.ch-product-single');
		if (product) {
			const description = product.querySelector('.ch-product-description .elementor-widget-theme-post-content');
			if (description && description.textContent.trim().length > 600 && description.scrollHeight > 440) {
				description.id = 'product-description-body'; description.classList.add('ch-description-collapsible');
				const more = document.createElement('button'); more.type = 'button'; more.className = 'ch-description-toggle'; more.setAttribute('aria-controls', description.id);
				const expand = open => { description.classList.toggle('is-expanded', open); more.setAttribute('aria-expanded', String(open)); more.textContent = open ? 'نمایش کمتر' : 'توضیحات کامل محصول'; };
				more.addEventListener('click', () => expand(more.getAttribute('aria-expanded') !== 'true'));
				description.addEventListener('focusin', () => expand(true));
				description.after(more); expand(false);
			}
			const sections = [['.ch-product-description', 'معرفی', 'product-intro'], ['.ch-product-facts-widget', 'مشخصات', 'product-specs'], ['.ch-product-tabs-widget', 'دیدگاه‌ها', 'product-reviews']];
			const nav = document.createElement('nav'); nav.className = 'ch-product-section-nav'; nav.setAttribute('aria-label', 'بخش‌های محصول');
			sections.forEach(([selector, label, id]) => { const section = product.querySelector(selector); if (!section || !section.textContent.trim()) return; section.id = id; const link = document.createElement('a'); link.href = `#${id}`; link.textContent = label; nav.append(link); });
			const layout = product.querySelector('.ch-product-layout'); if (layout && nav.children.length) layout.after(nav);
			const syncSections = () => {
				const links = [...nav.querySelectorAll('a')];
				const current = links.filter(link => document.getElementById(link.hash.slice(1)).getBoundingClientRect().top <= 170).at(-1) || links[0];
				links.forEach(link => { if (link === current) link.setAttribute('aria-current', 'location'); else link.removeAttribute('aria-current'); });
			};
			window.addEventListener('scroll', syncSections, { passive: true }); syncSections();
		}
	}

	document.addEventListener('DOMContentLoaded', enhanceCatalogSorting, { once: true });
	if (document.readyState !== 'loading') enhanceCatalogSorting();

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', onReady, { once: true });
	} else {
		onReady();
	}
}());
