(function () {
	'use strict';

	var config = window.ChidemoonCompare || {};
	if (!config.key) return;

	var memory = [];
	var persistenceFailed = false;
	var persistenceNoticeShown = false;
	var searchTimer = null;
	var searchController = null;

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

	function syncControls(items) {
		document.querySelectorAll('[data-compare-product]').forEach(function (control) {
			var active = selected(control.dataset.compareProduct, items);
			control.classList.toggle('is-selected', active);
			if (control.classList.contains('chidemoon-compare-control')) {
				control.setAttribute('aria-pressed', active ? 'true' : 'false');
				var label = control.querySelector('span');
				if (label) label.textContent = active ? config.labels.removed : config.labels.added;
			} else if (control.classList.contains('chidemoon-comparison-search__result')) {
				var action = control.querySelector('small');
				if (action) action.textContent = active ? config.labels.removed : config.labels.added;
			}
		});
	}

	function announce(message) {
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
		var pageIds = new URLSearchParams(window.location.search).get('products');
		if (!pageIds) return;
		var allowed = pageIds.split(',').map(function (id) { return Number(id); }).filter(function (id) { return id > 0; }).slice(0, config.maximum);
		var current = read();
		fetch(config.restUrl + '?ids=' + encodeURIComponent(allowed.join(',')))
			.then(function (response) { if (!response.ok) throw new Error('selection'); return response.json(); })
			.then(function (eligible) {
				var validIds = eligible.map(function (product) { return Number(product.id); });
				var invalidPersisted = current.filter(function (item) { return allowed.indexOf(Number(item.id)) !== -1 && validIds.indexOf(Number(item.id)) === -1; });
				current = current.filter(function (item) { return allowed.indexOf(Number(item.id)) === -1 || validIds.indexOf(Number(item.id)) !== -1; });
				eligible.forEach(function (product) {
					var existing = current.filter(function (item) { return Number(item.id) === Number(product.id); })[0];
					if (existing) existing.name = product.title || existing.name;
					else current.push({ id: product.id, name: product.title || '' });
				});
				if (invalidPersisted.length) announce(config.labels.staleSelection);
				write(current);
				refresh();
			})
			.catch(function () { announce(config.labels.searchError); });
	}

	function setBarOffset(bar) {
		if (bar.hidden) {
			document.documentElement.style.removeProperty('--chidemoon-compare-bar-height');
			return;
		}
		document.documentElement.style.setProperty('--chidemoon-compare-bar-height', Math.ceil(bar.getBoundingClientRect().height) + 'px');
	}

	function renderBar(items) {
		var bar = document.querySelector('.chidemoon-compare-bar');
		if (!bar) {
			bar = document.createElement('aside');
			bar.className = 'chidemoon-compare-bar';
			bar.setAttribute('aria-label', config.labels.compare);
			bar.innerHTML = '<div class="chidemoon-compare-bar__summary" aria-live="polite"></div><div class="chidemoon-compare-bar__items"></div><div class="chidemoon-compare-bar__actions"><button type="button" class="chidemoon-compare-bar__clear"></button><button type="button" class="chidemoon-compare-bar__go"></button></div>';
			document.body.appendChild(bar);
			bar.addEventListener('click', function (event) {
				var remove = event.target.closest('[data-remove-compare]');
				if (remove) {
					write(read().filter(function (item) { return Number(item.id) !== Number(remove.dataset.removeCompare); }));
					refresh();
					return;
				}
				if (event.target.closest('.chidemoon-compare-bar__clear')) {
					write([]);
					refresh();
					return;
				}
				if (event.target.closest('.chidemoon-compare-bar__go')) {
					var selectedItems = read();
					if (selectedItems.length < 2) {
						bar.querySelector('.chidemoon-compare-bar__summary').textContent = config.labels.needMore;
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
			if (window.ResizeObserver) new ResizeObserver(function () { setBarOffset(bar); }).observe(bar);
			window.addEventListener('resize', function () { setBarOffset(bar); });
		}

		bar.hidden = items.length === 0;
		document.documentElement.classList.toggle('has-chidemoon-compare-bar', !bar.hidden);
		if (!items.length) {
			setBarOffset(bar);
			return;
		}
		bar.querySelector('.chidemoon-compare-bar__summary').textContent = items.length + ' ' + config.labels.count;
		bar.querySelector('.chidemoon-compare-bar__items').innerHTML = items.map(function (item) {
			return '<button type="button" data-remove-compare="' + Number(item.id) + '" aria-label="' + escapeHtml((item.name || '') + ' — ' + config.labels.removed) + '">' + escapeHtml(item.name || ('#' + item.id)) + ' ×</button>';
		}).join('');
		bar.querySelector('.chidemoon-compare-bar__clear').textContent = config.labels.clear;
		var go = bar.querySelector('.chidemoon-compare-bar__go');
		go.textContent = config.labels.compare;
		go.disabled = items.length < 2;
		setBarOffset(bar);
	}

	function syncStatus(items) {
		var strip = document.querySelector('[data-comparison-status]');
		if (!strip) return;
		strip.hidden = items.length === 0;
		if (!items.length) return;
		var count = strip.querySelector('[data-comparison-status-count]');
		if (count) count.textContent = items.length + ' ' + config.labels.count + (items.length < 2 ? ' — ' + config.labels.needMore : '');
		var chips = strip.querySelector('[data-comparison-status-chips]');
		if (chips) {
			chips.innerHTML = items.map(function (item) {
				return '<span class="chidemoon-comparison-status__chip">' + escapeHtml(item.name || ('#' + item.id)) + '</span>';
			}).join('');
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
		if (!input || !results || !config.restUrl) return;
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
							var active = selected(product.id);
								return '<button type="button" class="chidemoon-comparison-search__result' + (active ? ' is-selected' : '') + '" data-compare-product="' + Number(product.id) + '" data-compare-name="' + escapeHtml(product.title || ('#' + product.id)) + '"><span>' + escapeHtml(product.title || ('#' + product.id)) + '</span><small>' + escapeHtml(active ? config.labels.removed : config.labels.added) + '</small></button>';
						}).join('');
					})
					.catch(function (error) {
						if (error.name === 'AbortError') return;
						if (input.value.trim() === term) renderSearchMessage(results, config.labels.searchError, 'chidemoon-comparison-search__empty');
					});
			}, 220);
		});
	}

	function bind() {
		var statusCta = document.querySelector('[data-comparison-status] .chidemoon-comparison-status__cta');
		if (statusCta) {
			statusCta.addEventListener('click', function (event) {
				var table = document.getElementById('chidemoon-comparison-table');
				if (!table || !document.querySelector('.chidemoon-comparison-table')) return;
				event.preventDefault();
				table.scrollIntoView({ behavior: 'smooth', block: 'start' });
			});
		}
		document.addEventListener('click', function (event) {
			var tableRemove = event.target.closest('.chidemoon-comparison-table__remove');
			if (tableRemove) {
				event.preventDefault();
				write(read().filter(function (item) { return Number(item.id) !== Number(tableRemove.dataset.compareProduct); }));
				var remaining = read();
				window.location.assign(remaining.length ? comparisonUrl(remaining) : config.compareUrl + '#chidemoon-comparison-table');
				return;
			}
			var control = event.target.closest('.chidemoon-compare-control, .chidemoon-comparison-search__result');
			if (!control) return;
			event.preventDefault();
			var id = Number(control.dataset.compareProduct);
			var items = read();
			if (selected(id, items)) {
				write(items.filter(function (item) { return Number(item.id) !== id; }));
			} else if (items.length < config.maximum) {
				items.push({ id: id, name: control.dataset.compareName || '' });
				write(items);
			} else {
				announce(config.labels.full);
				return;
			}
			refresh();
		});
		syncPageSelection();
		bindSearch();
		refresh();
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind); else bind();
})();
