(function () {
	'use strict';

	var config = window.ChidemoonCompare || {};
	if (!config.key) return;

	var memory = [];
	var persistenceFailed = false;
	var persistenceNoticeShown = false;
	var searchTimer = null;
	var searchController = null;
	var pageSelectionPending = false;
	var pageSelectionFailed = false;
	var tableRequest = null;
	var tableRevision = 0;

	function normalize(items) {
		return Array.isArray(items) ? items.filter(function (item) { return item && Number(item.id) > 0; }).slice(0, config.maximum) : [];
	}

	function read() {
		if (persistenceFailed) return memory.slice();
		try {
			var stored = normalize(JSON.parse(localStorage.getItem(config.key) || '[]'));
			memory = stored.slice();
			return stored;
		} catch (error) {
			persistenceFailed = true;
			return memory.slice();
		}
	}

	function write(items) {
		memory = normalize(items);
		if (persistenceFailed) return memory.slice();
		try {
			localStorage.setItem(config.key, JSON.stringify(memory));
		} catch (error) {
			persistenceFailed = true;
		}
		return memory.slice();
	}

	function selected(id, items) {
		return (items || read()).some(function (item) { return Number(item.id) === Number(id); });
	}

	function escapeHtml(value) {
		var node = document.createElement('span');
		node.textContent = value;
		return node.innerHTML;
	}

	function faDigits(value) {
		var text = String(value);
		return /^[0-9]+$/.test(text) ? text.replace(/[0-9]/g, function (digit) { return '۰۱۲۳۴۵۶۷۸۹'.charAt(digit); }) : text;
	}

	function thumbHtml(item, className) {
		var name = (item.name || '').trim();
		if (item.image) {
			return '<img class="' + className + '" src="' + escapeHtml(item.image) + '" alt="" loading="lazy" width="40" height="40">';
		}
		return '<span class="' + className + ' ' + className + '--empty" aria-hidden="true">' + escapeHtml(name.charAt(0) || '#') + '</span>';
	}

	function showBarHint(bar, message) {
		if (!bar) return;
		var hint = bar.querySelector('.chidemoon-compare-bar__hint');
		if (!hint) return;
		hint.textContent = message;
		hint.hidden = false;
		setBarExpanded(bar, true);
	}

	function syncControls(items) {
		document.querySelectorAll('[data-compare-product]').forEach(function (control) {
			var active = selected(control.dataset.compareProduct, items);
			control.classList.toggle('is-selected', active);
			if (control.classList.contains('chidemoon-compare-control')) {
				control.setAttribute('aria-pressed', active ? 'true' : 'false');
				var label = control.querySelector('span');
				if (label) label.textContent = active
					? (control.dataset.compareSelectedLabel !== undefined ? control.dataset.compareSelectedLabel : config.labels.removed)
					: (control.dataset.compareLabel !== undefined ? control.dataset.compareLabel : config.labels.added);
			} else if (control.classList.contains('chidemoon-compare-single')) {
				control.setAttribute('aria-pressed', active ? 'true' : 'false');
				var singleLabel = control.querySelector('.chidemoon-compare-single__label');
				var singleHint = control.querySelector('.chidemoon-compare-single__hint');
				if (singleLabel) singleLabel.textContent = active
					? (control.dataset.compareSelectedLabel !== undefined ? control.dataset.compareSelectedLabel : config.labels.singleIn)
					: (control.dataset.compareLabel !== undefined ? control.dataset.compareLabel : config.labels.singleAdd);
				if (singleHint) {
					var hint = active
						? (control.dataset.compareSelectedHint !== undefined ? control.dataset.compareSelectedHint : config.labels.singleRemoveHint)
						: (control.dataset.compareHint !== undefined ? control.dataset.compareHint : config.labels.singleHint);
					singleHint.textContent = hint;
					singleHint.hidden = !hint;
				}
			} else if (control.classList.contains('chidemoon-comparison-search__result')) {
				var action = control.querySelector('small');
				if (action) action.textContent = active ? config.labels.removed : config.labels.added;
			}
		});
	}

	function announce(message) {
		var feedback = document.querySelector('[data-comparison-feedback]');
		if (feedback) { feedback.textContent = message; feedback.hidden = !message; }
		var notice = document.getElementById('chidemoon-compare-status');
		if (!notice) {
			notice = document.createElement('p');
			notice.id = 'chidemoon-compare-status';
			notice.className = 'chidemoon-sr-only';
			notice.setAttribute('role', 'status');
			notice.setAttribute('aria-live', 'polite');
			document.body.appendChild(notice);
		}
		notice.textContent = message;
	}

	function comparisonUrl(items) {
		var ids = items.map(function (item) { return item.id; }).join(',');
		var url = config.compareUrl + (config.compareUrl.indexOf('?') === -1 ? '?' : '&') + 'products=' + encodeURIComponent(ids);
		return url + '#chidemoon-comparison-table';
	}

	function syncPageSelection() {
		if (document.getElementById('chidemoon-comparison-table')) {
			write(displayedTableSelection());
			return;
		}
		var pageIds = new URLSearchParams(window.location.search).get('products');
		if (!pageIds) return;
		var allowed = pageIds.split(',').map(function (id) { return Number(id); }).filter(function (id, index, ids) { return Number.isInteger(id) && id > 0 && ids.indexOf(id) === index; }).slice(0, config.maximum);
		if (!allowed.length) {
			pageSelectionFailed = true;
			announce(config.labels.staleSelection);
			return;
		}
		pageSelectionPending = true;
		fetch(config.restUrl + '?ids=' + encodeURIComponent(allowed.join(',')))
			.then(function (response) { if (!response.ok) throw new Error('selection'); return response.json(); })
			.then(function (eligible) {
				var selected = allowed.map(function (id) {
					return eligible.find(function (product) { return Number(product.id) === id; });
				}).filter(Boolean).map(function (product) {
					return { id: product.id, name: product.title || '', image: product.image || '' };
				});
				if (selected.length < allowed.length) announce(config.labels.staleSelection);
					write(selected);
					pageSelectionPending = false;
					pageSelectionFailed = false;
					refresh();
				})
				.catch(function () {
					pageSelectionPending = false;
					var displayed = displayedTableSelection();
					if (displayed.length) write(displayed);
					else pageSelectionFailed = true;
					refresh();
					announce(config.labels.searchError);
				});
	}

	function displayedTableSelection() {
		var table = document.querySelector('.chidemoon-comparison-table');
		if (!table) return [];
		return Array.from(table.querySelectorAll('.chidemoon-comparison-table__remove')).map(function (button) {
			var image = button.parentElement.querySelector('img');
			return { id: Number(button.dataset.compareProduct), name: button.dataset.compareName || '', image: image ? image.currentSrc || image.src : '' };
		}).filter(function (item) { return Number.isInteger(item.id) && item.id > 0; }).slice(0, config.maximum);
	}

	function setComparisonHistory(items, push) {
		var url = new URL(location.href);
		url.searchParams.set('products', items.map(function (item) { return item.id; }).join(','));
		history[push ? 'pushState' : 'replaceState'](null, '', url);
	}

	function removeTableProduct(id) {
		var section = document.getElementById('chidemoon-comparison-table');
		if (!section) return false;
		++tableRevision;
		if (tableRequest) tableRequest.abort();
		section.removeAttribute('aria-busy');
		var table = section.querySelector('table');
		var button = table && Array.from(table.querySelectorAll('.chidemoon-comparison-table__remove')).find(function (b) { return Number(b.dataset.compareProduct) === Number(id); });
		if (button) {
			var index = button.parentElement.cellIndex;
			Array.from(table.rows).forEach(function (row) { if (row.cells[index]) row.deleteCell(index); });
			table.dataset.productCount = table.querySelectorAll('.chidemoon-comparison-table__remove').length;
			if (table.dataset.productCount === '0') section.innerHTML = '<p class="chidemoon-compare-empty">محصولی انتخاب نشده است؛ از نوار جست‌وجو محصول اضافه کنید.</p>';
		}
		write(displayedTableSelection());
		setComparisonHistory(read(), true);
		bindComparisonTools(); refresh();
		var recommendations = section.querySelector('[data-comparison-recommendations]');
		if (recommendations) {
			recommendations.remove();
			if (read().length) updateComparisonTable(read(), false);
		}
		announce('محصول از جدول حذف شد.');
		return true;
	}

	function updateComparisonTable(items, push) {
		var section = document.getElementById('chidemoon-comparison-table');
		if (!section || !config.tableUrl) return;
		if (tableRequest) tableRequest.abort();
		tableRequest = new AbortController();
		var revision = ++tableRevision;
		section.setAttribute('aria-busy', 'true');
		announce(config.labels.loading);
		fetch(config.tableUrl + '?ids=' + encodeURIComponent(items.map(function (item) { return item.id; }).join(',')), {signal:tableRequest.signal})
			.then(function (response) { if (!response.ok) throw new Error('table'); return response.json(); })
			.then(function (data) {
				if (revision !== tableRevision) return;
				var doc = new DOMParser().parseFromString(data.html, 'text/html');
				var replacement = doc.getElementById('chidemoon-comparison-table');
				if (!replacement) throw new Error('table');
				section.replaceWith(document.importNode(replacement, true));
				write(displayedTableSelection());
				if (push) setComparisonHistory(read(), true);
				bindComparisonTools(); refresh(); announce('جدول مقایسه به‌روز شد.');
			})
			.catch(function (error) {
				if (error.name === 'AbortError' || revision !== tableRevision) return;
				section.removeAttribute('aria-busy');
				write(displayedTableSelection()); refresh(); announce(config.labels.searchError);
			});
	}

	function setBarOffset(bar) {
		if (bar.hidden) {
			document.documentElement.style.removeProperty('--chidemoon-compare-bar-height');
			return;
		}
		document.documentElement.style.setProperty('--chidemoon-compare-bar-height', Math.ceil(bar.querySelector('.chidemoon-compare-bar__toolbar').getBoundingClientRect().height) + 'px');
	}

	function setBarExpanded(bar, expanded) {
		var summary = bar.querySelector('.chidemoon-compare-bar__summary');
		var details = bar.querySelector('.chidemoon-compare-bar__details');
		details.hidden = !expanded;
		summary.setAttribute('aria-expanded', expanded ? 'true' : 'false');
		summary.setAttribute('aria-label', summary.dataset.countLabel + '، ' + (expanded ? config.labels.collapse : config.labels.expand));
		bar.classList.toggle('is-expanded', expanded);
		setBarOffset(bar);
	}

	function renderBar(items) {
		// The comparison page already has an inline selection summary.
		if (document.querySelector('[data-comparison-status]')) {
			var existingBar = document.querySelector('.chidemoon-compare-bar');
			if (existingBar) existingBar.remove();
			document.documentElement.classList.remove('has-chidemoon-compare-bar');
			document.documentElement.style.removeProperty('--chidemoon-compare-bar-height');
			return;
		}
		var bar = document.querySelector('.chidemoon-compare-bar');
		if (!bar) {
			bar = document.createElement('aside');
			bar.className = 'chidemoon-compare-bar';
			bar.setAttribute('aria-label', config.labels.compare);
			bar.innerHTML = "<div class=\"chidemoon-compare-bar__details\" id=\"chidemoon-compare-bar-details\" hidden><div class=\"chidemoon-compare-bar__heading\"><span>محصولات انتخاب‌شده</span><button type=\"button\" class=\"chidemoon-compare-bar__clear\"></button></div><ul class=\"chidemoon-compare-bar__items\"></ul><p class=\"chidemoon-compare-bar__hint\" hidden></p></div><div class=\"chidemoon-compare-bar__toolbar\"><button type=\"button\" class=\"chidemoon-compare-bar__summary\" aria-expanded=\"false\" aria-controls=\"chidemoon-compare-bar-details\"><svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.6\" stroke-linecap=\"round\" stroke-linejoin=\"round\" aria-hidden=\"true\"><path d=\"M4 7h15m-4-4 4 4-4 4M20 17H5m4-4-4 4 4 4\"/></svg><span>مشاهده محصولات</span><svg class=\"chidemoon-compare-bar__toggle-icon\" viewBox=\"0 0 20 20\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.5\" aria-hidden=\"true\"><path d=\"m5 12 5-5 5 5\"/></svg></button><button type=\"button\" class=\"chidemoon-compare-bar__go\"><span></span></button><span class=\"chidemoon-compare-bar__single-hint\" hidden>محصول دیگری اضافه کنید</span></div><span class=\"chidemoon-sr-only chidemoon-compare-bar__live\" aria-live=\"polite\"></span>";
			document.body.appendChild(bar);
			bar.addEventListener('error', function (event) {
				var image = event.target;
				if (!image.matches || !image.matches('img.chidemoon-compare-bar__thumb')) return;
				var placeholder = document.createElement('span');
				placeholder.className = 'chidemoon-compare-bar__thumb chidemoon-compare-bar__thumb--empty';
				placeholder.setAttribute('aria-hidden', 'true');
				var name = image.parentElement.querySelector('.chidemoon-compare-bar__chip-name');
				placeholder.textContent = name ? name.textContent.trim().charAt(0) : '';
				image.replaceWith(placeholder);
			}, true);
			bar.addEventListener('click', function (event) {
				if (event.target.closest('.chidemoon-compare-bar__summary')) {
					setBarExpanded(bar, bar.querySelector('.chidemoon-compare-bar__details').hidden);
					return;
				}
				var remove = event.target.closest('[data-remove-compare]');
				if (remove) {
					if (removeTableProduct(remove.dataset.removeCompare)) return;
					write(read().filter(function (item) { return Number(item.id) !== Number(remove.dataset.removeCompare); }));
					refresh();
					var nextFocus = bar.hidden ? document.querySelector('[data-compare-product]') : bar.querySelector('.chidemoon-compare-bar__summary');
					if (nextFocus) nextFocus.focus();
					return;
				}
				if (event.target.closest('.chidemoon-compare-bar__clear')) {
					if (document.getElementById('chidemoon-comparison-table')) {
						displayedTableSelection().forEach(function (item) { removeTableProduct(item.id); });
						return;
					}
					write([]);
					refresh();
					var firstControl = document.querySelector('[data-compare-product]');
					if (firstControl) firstControl.focus();
					return;
				}
				if (event.target.closest('.chidemoon-compare-bar__go')) {
					var selectedItems = read();
					if (selectedItems.length < 2) {
						showBarHint(bar, config.labels.needMore);
						announce(config.labels.needMore);
						return;
					}
					var tableSection = document.getElementById('chidemoon-comparison-table');
					if (tableSection && document.querySelector('.chidemoon-comparison-table')) {
						tableSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
						return;
					}
					window.location.assign(comparisonUrl(selectedItems));
				}
			});
			bar.addEventListener('keydown', function (event) {
				if (event.key === 'Escape' && !bar.querySelector('.chidemoon-compare-bar__details').hidden) {
					setBarExpanded(bar, false);
					bar.querySelector('.chidemoon-compare-bar__summary').focus();
				}
			});
			if (window.ResizeObserver) {
				var barObserver = new ResizeObserver(function () { setBarOffset(bar); });
				barObserver.observe(bar);
			}
			window.addEventListener('resize', function () { setBarOffset(bar); });
			document.addEventListener('click', function (event) {
				var inside = event.composedPath ? event.composedPath().indexOf(bar) !== -1 : bar.contains(event.target);
				if (!inside && bar.classList.contains('is-expanded')) setBarExpanded(bar, false);
			});
		}

		bar.hidden = items.length === 0;
		document.documentElement.classList.toggle('has-chidemoon-compare-bar', !bar.hidden);
		if (!items.length) {
			setBarExpanded(bar, false);
			setBarOffset(bar);
			return;
		}
		var summary = bar.querySelector('.chidemoon-compare-bar__summary');
		summary.dataset.countLabel = faDigits(items.length) + ' ' + config.labels.count;
		bar.querySelector('.chidemoon-compare-bar__live').textContent = summary.dataset.countLabel;
		bar.querySelector('.chidemoon-compare-bar__items').innerHTML = items.map(function (item) {
			return '<li class="chidemoon-compare-bar__chip">' + thumbHtml(item, 'chidemoon-compare-bar__thumb') + '<span class="chidemoon-compare-bar__chip-name" dir="auto" title="' + escapeHtml(item.name || ('#' + item.id)) + '">' + escapeHtml(item.name || ('#' + item.id)) + '</span><button type="button" class="chidemoon-compare-bar__remove" data-remove-compare="' + Number(item.id) + '" aria-label="' + escapeHtml((item.name || '') + ' — ' + (config.labels.removeItem || config.labels.removed)) + '"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="m6 6 8 8M14 6l-8 8"/></svg></button></li>';
		}).join('');
		bar.querySelector('.chidemoon-compare-bar__clear').textContent = config.labels.clear;
		var go = bar.querySelector('.chidemoon-compare-bar__go');
		var hint = bar.querySelector('.chidemoon-compare-bar__hint');
		go.querySelector('span').textContent = config.labels.compare;
		go.hidden = items.length < 2;
		hint.hidden = true;
		bar.querySelector('.chidemoon-compare-bar__single-hint').hidden = items.length >= 2;
		setBarExpanded(bar, !bar.querySelector('.chidemoon-compare-bar__details').hidden);
	}

	function syncStatus(items) {
		var strip = document.querySelector('[data-comparison-status]');
		if (!strip) return;
		strip.hidden = pageSelectionPending || pageSelectionFailed || items.length === 0;
		if (strip.hidden) return;
		var count = strip.querySelector('[data-comparison-status-count]');
		if (count) count.textContent = faDigits(items.length) + ' ' + config.labels.count + (items.length < 2 ? ' — ' + config.labels.needMore : '');
		var chips = strip.querySelector('[data-comparison-status-chips]');
		if (chips) {
			chips.innerHTML = items.map(function (item) {
				return '<button type="button" class="chidemoon-comparison-status__chip" data-comparison-remove="' + Number(item.id) + '" aria-label="' + escapeHtml('حذف ' + item.name + ' از مقایسه') + '">' + thumbHtml(item, 'chidemoon-comparison-status__chip-thumb') + '<span dir="auto">' + escapeHtml(item.name || ('#' + item.id)) + '</span><span aria-hidden="true">×</span></button>';
			}).join('');
		}
		var cta = strip.querySelector('.chidemoon-comparison-status__cta');
		if (cta) {
			cta.hidden = items.length < 2;
			if (!cta.hidden) cta.href = comparisonUrl(items);
			var displayed = displayedTableSelection().map(function (item) { return Number(item.id); });
			var same = displayed.length === items.length && items.every(function (item, index) { return Number(item.id) === displayed[index]; });
			cta.textContent = same ? 'دیدن جدول مقایسه' : 'به‌روزرسانی مقایسه';
			strip.classList.toggle('is-current', same);
		}
	}

	function refresh() {
		var items = read();
		syncControls(items);
		renderBar(items);
		syncStatus(items);
		if (persistenceFailed && !persistenceNoticeShown) {
			persistenceNoticeShown = true;
			announce(config.labels.sessionOnly);
		}
	}

	function renderSearchMessage(results, message, className) {
		results.hidden = false;
		results.innerHTML = '<p class="' + className + '">' + escapeHtml(message) + '</p>';
	}

	function bindSearch() {
		var input = document.querySelector('[data-comparison-search-input]');
		var results = document.querySelector('[data-comparison-search-results]');
		if (!input || !results || !config.restUrl || input.dataset.searchReady) return;
		input.dataset.searchReady = 'true';
		var home = input.closest('[data-home-editorial-comparison]');
		input.addEventListener('input', function () {
			var term = input.value.trim();
			window.clearTimeout(searchTimer);
			if (searchController) searchController.abort();
			if (term.length < 2) {
				results.hidden = true;
				results.innerHTML = '';
				return;
			}
			searchTimer = window.setTimeout(function () {
				searchController = window.AbortController ? new AbortController() : null;
				renderSearchMessage(results, config.labels.loading, 'chidemoon-comparison-search__empty');
				fetch(config.restUrl + '?search=' + encodeURIComponent(term), searchController ? { signal: searchController.signal } : {})
					.then(function (response) { if (!response.ok) throw new Error('search'); return response.json(); })
					.then(function (products) {
						if (input.value.trim() !== term) return;
						if (!products.length) {
							renderSearchMessage(results, config.labels.noResults, 'chidemoon-comparison-search__empty');
							return;
						}
						results.hidden = false;
						results.innerHTML = products.map(function (product) {
							var active = home ? homeIds(home).includes(Number(product.id)) : selected(product.id);
							var thumb = product.image ? '<img class="chidemoon-comparison-search__thumb" src="' + escapeHtml(product.image) + '" alt="" loading="lazy" width="40" height="40">' : '';
							return '<button type="button" class="chidemoon-comparison-search__result' + (active ? ' is-selected' : '') + '" '+(home?'data-home-comparison-add':'data-compare-product')+'="' + Number(product.id) + '" data-compare-name="' + escapeHtml(product.title || ('#' + product.id)) + '" data-compare-image="' + escapeHtml(product.image || '') + '">' + thumb + '<span dir="auto">' + escapeHtml(product.title || ('#' + product.id)) + '</span><small>' + escapeHtml(active ? config.labels.removed : config.labels.added) + '</small></button>';
						}).join('');
					})
					.catch(function (error) {
						if (error.name === 'AbortError') return;
						if (input.value.trim() === term) renderSearchMessage(results, config.labels.searchError, 'chidemoon-comparison-search__empty');
					});
			}, 220);
		});
	}

	function homeIds(home) {
		return Array.from(home.querySelectorAll('[data-home-comparison-remove]')).map(function(b){return Number(b.dataset.homeProduct);});
	}

	function updateHomeTable(home, ids) {
		if (home.tableController) home.tableController.abort();
		var controller = new AbortController(); home.tableController = controller;
		var feedback = home.querySelector('[data-home-comparison-feedback]');
		feedback.classList.remove('chidemoon-sr-only');
		feedback.textContent = config.labels.loading;
		home.setAttribute('aria-busy','true');
		fetch(config.tableUrl+'?ids='+encodeURIComponent(ids.join(',')),{signal:controller.signal})
			.then(function(r){if(!r.ok)throw Error('table');return r.json();})
			.then(function(data){
				if(home.tableController!==controller)return;
				var doc=new DOMParser().parseFromString(data.html,'text/html'),wrap=doc.querySelector('.chidemoon-comparison-table-wrap');
				if(!wrap)throw Error('table');
				wrap.querySelectorAll('.chidemoon-comparison-table__remove').forEach(function(b){b.dataset.homeComparisonRemove='';b.dataset.homeProduct=b.dataset.compareProduct;b.removeAttribute('data-compare-product');b.parentElement.prepend(b);});
				home.querySelector('.chidemoon-comparison-table-wrap').replaceWith(document.importNode(wrap,true));
				home.querySelector('.ch-home-comparison-intro').textContent='محصولات انتخابی تو، کنار هم';
				home.querySelector('.ch-home-comparison-intro').hidden=false;
				home.querySelector('.chidemoon-comparison-scroll-hint').hidden=false;
				home.querySelector('.ch-home-comparison-note').hidden=true;
				home.querySelectorAll('[data-home-comparison-add]').forEach(function(b){var active=ids.includes(Number(b.dataset.homeComparisonAdd));b.classList.toggle('is-selected',active);b.querySelector('small').textContent=active?config.labels.removed:config.labels.added;});
				feedback.textContent='محصول به جدول مقایسه اضافه شد.';
			})
			.catch(function(error){if(error.name!=='AbortError')feedback.textContent=config.labels.searchError;})
			.finally(function(){if(home.tableController===controller)home.removeAttribute('aria-busy');});
	}

	function backfillImages() {
		if (!config.restUrl) return;
		var pending = read().filter(function (item) { return !item.image; });
		if (!pending.length) return;
		fetch(config.restUrl + '?ids=' + encodeURIComponent(pending.map(function (item) { return item.id; }).join(',')))
			.then(function (response) { if (!response.ok) throw new Error('images'); return response.json(); })
			.then(function (products) {
				var current = read();
				var changed = false;
				products.forEach(function (product) {
					current.forEach(function (item) {
						if (Number(item.id) === Number(product.id) && !item.image && product.image) {
							item.image = product.image;
							changed = true;
						}
					});
				});
				if (changed) {
					write(current);
					refresh();
				}
			})
			.catch(function () {});
	}

	function bind() {
		bindComparisonTools();
		var statusCta = document.querySelector('[data-comparison-status] .chidemoon-comparison-status__cta');
		if (statusCta) {
			statusCta.addEventListener('click', function (event) {
				var table = document.getElementById('chidemoon-comparison-table');
				if (!table || !document.querySelector('.chidemoon-comparison-table')) return;
				var displayed = Array.from(table.querySelectorAll('.chidemoon-comparison-table__remove')).map(function (button) { return Number(button.dataset.compareProduct); });
				var chosen = read().map(function (item) { return Number(item.id); });
				if (displayed.length !== chosen.length || displayed.some(function (id, index) { return id !== chosen[index]; })) return;
				event.preventDefault();
				table.scrollIntoView({ behavior: 'smooth', block: 'start' });
			});
		}
		document.addEventListener('click', function (event) {
			var homeAdd = event.target.closest('[data-home-comparison-add]');
			if(homeAdd){
				var owner=homeAdd.closest('[data-home-editorial-comparison]'),ids=homeIds(owner),added=Number(homeAdd.dataset.homeComparisonAdd);
				if(owner.getAttribute('aria-busy')==='true')return;
				if(ids.includes(added))return;
				if(ids.length>=config.maximum){owner.querySelector('[data-home-comparison-feedback]').textContent=config.labels.full;return;}
				updateHomeTable(owner,ids.concat(added));return;
			}
			var homeRemove = event.target.closest('[data-home-comparison-remove]');
			var homeReset = event.target.closest('[data-home-comparison-reset]');
			if (homeRemove || homeReset) {
				var home = (homeRemove || homeReset).closest('[data-home-editorial-comparison]');
				if (home.tableController) { home.tableController.abort(); home.tableController=null; home.removeAttribute('aria-busy'); }
				if (homeReset) { home.innerHTML = home.initialMarkup; home.querySelector('[data-comparison-search-input]').removeAttribute('data-search-ready'); bindSearch(); return; }
				if (!home.initialMarkup) home.initialMarkup = home.innerHTML;
				var homeTable = home.querySelector('table'), column = homeRemove.parentElement.cellIndex;
				Array.from(homeTable.rows).forEach(function (row) { if (row.cells[column]) row.deleteCell(column); });
				var remaining = homeTable.querySelectorAll('[data-home-comparison-remove]');
				homeTable.dataset.productCount = remaining.length;
				if (!remaining.length) {
					home.querySelector('.chidemoon-comparison-table-wrap').hidden=true;
					home.querySelector('.ch-home-comparison-intro').hidden=true;
					home.querySelector('.chidemoon-comparison-scroll-hint').hidden=true;
					home.querySelector('.ch-home-comparison-note').hidden=true;
					home.querySelector('[data-home-comparison-search]').hidden=false;
					home.querySelector('[data-comparison-search-results]').hidden=true;
				}
				var reset = home.querySelector('[data-home-comparison-reset]'); reset.hidden = false;
				home.querySelector('[data-home-comparison-feedback]').textContent = 'محصول از جدول حذف شد.';
				(remaining[0] || home.querySelector('[data-comparison-search-input]')).focus();
				return;
			}
			var chipRemove = event.target.closest('[data-comparison-remove]');
			if (chipRemove) {
				if (removeTableProduct(chipRemove.dataset.comparisonRemove)) return;
				write(read().filter(function (item) { return Number(item.id) !== Number(chipRemove.dataset.comparisonRemove); }));
				refresh();
				var input = document.querySelector('[data-comparison-search-input]');
				if (input) input.focus();
				return;
			}
			var tableRemove = event.target.closest('.chidemoon-comparison-table__remove');
			if (tableRemove) {
				event.preventDefault();
				removeTableProduct(tableRemove.dataset.compareProduct);
				return;
			}
			var control = event.target.closest('.chidemoon-compare-control, .chidemoon-compare-single, .chidemoon-comparison-search__result');
			if (!control) return;
			event.preventDefault();
			var id = Number(control.dataset.compareProduct);
			var items = read();
			if (selected(id, items)) {
				if (removeTableProduct(id)) return;
				write(items.filter(function (item) { return Number(item.id) !== id; }));
			} else if (items.length < config.maximum) {
				items.push({ id: id, name: control.dataset.compareName || '', image: control.dataset.compareImage || '' });
				write(items);
			} else {
				announce(config.labels.full);
				showBarHint(document.querySelector('.chidemoon-compare-bar'), config.labels.full);
				return;
			}
			pageSelectionFailed = false;
			if (document.getElementById('chidemoon-comparison-table')) updateComparisonTable(read(), true);
			refresh();
		});
		syncPageSelection();
		bindSearch();
		refresh();
		backfillImages();
		window.addEventListener('popstate', function () {
			if (!document.getElementById('chidemoon-comparison-table')) return;
			var ids = new URLSearchParams(location.search).get('products') || '';
			var items = ids.split(',').filter(function (id) { return /^[1-9][0-9]*$/.test(id); }).slice(0,config.maximum).map(function (id) { return {id:Number(id),name:'',image:''}; });
			updateComparisonTable(items, false);
		});
	}

	function bindComparisonTools() {
		var section = document.querySelector('.chidemoon-comparison-table-section');
		var tools = section && section.querySelector('[data-comparison-tools]');
		if (!tools) return;
		var table = section.querySelector('table');
		if (!table) return;
		var rows = Array.from(table.querySelectorAll('tbody tr'));
		var comparable = rows.slice(0, -1);
		var normalizeValue = function (value) {
			return value.replace(/[\u200c\u200e\u200f]/g, '').replace(/\s+/g, ' ').trim();
		};
		comparable.forEach(function (row) {
			var values = Array.from(row.querySelectorAll('td')).map(function (cell) { return normalizeValue(cell.textContent); });
			row.dataset.different = values.some(function (value) { return value !== values[0]; }) ? 'true' : 'false';
			row.classList.toggle('is-different', row.dataset.different === 'true');
		});
		var summary = section.querySelector('.chidemoon-comparison-toolbar p');
		var differenceCount = comparable.filter(function (row) { return row.dataset.different === 'true'; }).length;
		if (summary) summary.textContent = faDigits(differenceCount) + ' تفاوت در اطلاعات ثبت‌شده · ' + faDigits(comparable.length - differenceCount) + ' ویژگی مشترک';
		// Keep removal before the image, independent of product-title length.
		table.querySelectorAll('.chidemoon-comparison-table__remove').forEach(function (button) {
			button.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M6 18 18 6"/></svg>';
			button.parentElement.prepend(button);
		});
		var copyResetTimer;
		var copyLabel = tools.querySelector('[data-comparison-share]').textContent;
		tools.querySelector('[data-comparison-share]').onclick = function () {
			var url = comparisonUrl(displayedTableSelection());
			if (navigator.clipboard && window.isSecureContext) {
				var button = this;
				navigator.clipboard.writeText(new URL(url, window.location.href).href).then(function () { clearTimeout(copyResetTimer); button.textContent = 'لینک کپی شد ✓'; announce('لینک مقایسه کپی شد.'); copyResetTimer = setTimeout(function () { button.textContent = copyLabel; }, 2000); }, function () { window.prompt('لینک مقایسه را کپی کنید', url); });
			} else window.prompt('لینک مقایسه را کپی کنید', url);
		};
		tools.hidden = false;
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind); else bind();
})();
