(function () {
	'use strict';

	function text(value) {
		return typeof value === 'string' ? value : '';
	}

	function ids(value) {
		if (!text(value).trim()) {
			return [];
		}
		return text(value).split(',').map(function (part) {
			return part.trim();
		}).filter(function (part) {
			return /^[1-9][0-9]*$/.test(part);
		}).map(function (part) {
			return Number(part);
		});
	}

	function request(path, payload, idempotencyKey) {
		var config = window.ChidemoonAiAdmin;
		var headers = {
			'Content-Type': 'application/json',
			'X-WP-Nonce': config.nonce
		};
		if (idempotencyKey) {
			headers['Idempotency-Key'] = idempotencyKey;
		}
		return fetch(config.root + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: headers,
			body: JSON.stringify(payload || {})
		}).then(function (response) {
			return response.json().then(function (body) {
				if (!response.ok) {
					throw new Error(text(body.message) || config.error);
				}
				return body;
			});
		});
	}

	function result(container, message, isError) {
		container.replaceChildren();
		var paragraph = document.createElement('p');
		paragraph.textContent = message;
		paragraph.style.color = isError ? '#b32d2e' : '#2271b1';
		container.appendChild(paragraph);
	}

	function adminNotice(message) {
		var wrap = document.querySelector('.wrap');
		if (!wrap) {
			return;
		}
		var notice = document.createElement('div');
		notice.className = 'notice notice-error is-dismissible';
		var paragraph = document.createElement('p');
		paragraph.textContent = message;
		notice.appendChild(paragraph);
		wrap.insertBefore(notice, wrap.firstChild);
	}

	function uniqueKey() {
		if (window.crypto && typeof window.crypto.randomUUID === 'function') {
			return window.crypto.randomUUID();
		}
		return String(Date.now()) + '-' + String(Math.random()).slice(2);
	}

	function payloadFor(form, type) {
		var target = form.elements.target_post_id ? form.elements.target_post_id.value : '';
		var payload = {};
		if (target) {
			payload.target_post_id = Number(target);
		}
		if (type === 'text') {
			payload.kind = form.elements.kind.value;
			payload.source_post_ids = ids(form.elements.source_post_ids.value);
			payload.instructions = form.elements.instructions.value;
			if (form.elements.tone) payload.tone = form.elements.tone.value;
			if (form.elements.length) payload.length = form.elements.length.value;
			if (form.elements.lang) payload.lang = form.elements.lang.value;
		} else if (type === 'comparison') {
			payload.product_ids = ids(form.elements.product_ids.value);
			payload.instructions = form.elements.instructions.value;
		} else if (type === 'look') {
			payload.product_ids = ids(form.elements.product_ids.value);
			payload.room = form.elements.room ? form.elements.room.value : '';
			payload.style = form.elements.style ? form.elements.style.value : 'minimal';
			payload.source_attachment_ids = ids(form.elements.source_attachment_ids.value);
			payload.instructions = form.elements.instructions.value;
			payload.rights_attestation = form.elements.rights_attestation.checked;
		} else if (type === 'enrich') {
			payload.product_id = form.elements.product_id ? Number(form.elements.product_id.value) : 0;
			payload.use_source_url = form.elements.use_source_url ? form.elements.use_source_url.checked : true;
			payload.use_web = form.elements.use_web ? form.elements.use_web.checked : true;
			payload.instructions = form.elements.instructions.value;
		} else {
			payload.mode = form.elements.mode.value;
			payload.source_attachment_ids = ids(form.elements.source_attachment_ids.value);
			payload.prompt = form.elements.instructions.value;
			payload.rights_attestation = form.elements.rights_attestation.checked;
		}
		return payload;
	}

	function initForm(form) {
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			if (form.querySelector('[data-look-product-picker]') && !ids(form.elements.product_ids.value).length) {
				result(form.querySelector('.chidemoon-ai-job-form__result'), 'ابتدا حداقل یک محصول را جست‌وجو و اضافه کن.', true);
				return;
			}
			var type = form.getAttribute('data-chidemoon-ai-job');
			var output = form.querySelector('.chidemoon-ai-job-form__result');
			var button = form.querySelector('button[type="submit"]');
			button.disabled = true;
			result(output, 'Queueing AI job…', false);
			request('jobs/' + type, payloadFor(form, type), uniqueKey()).then(function (response) {
				var id = response && response.job ? response.job.id : '';
				result(output, window.ChidemoonAiAdmin.queued + (id ? ' #' + id : ''), false);
				window.setTimeout(function () { window.location.reload(); }, 1200);
			}).catch(function (error) {
				result(output, text(error.message) || window.ChidemoonAiAdmin.error, true);
				button.disabled = false;
			});
		});
	}

	function initReview(button) {
		button.addEventListener('click', function () {
			var action = button.getAttribute('data-chidemoon-ai-review');
			var jobId = button.getAttribute('data-job-id');
			var payload = {};
			if (action === 'approve' && button.closest('tr').querySelector('[data-ch-enrichment-field]')) {
				payload.approvedFields = Array.from(button.closest('tr').querySelectorAll('[data-ch-enrichment-field]:checked')).map(function (field) { return field.value; });
			}
			if (action === 'reject') {
				var reason = window.prompt('Optional rejection reason:');
				if (reason === null) {
					return;
				}
				payload.reason = reason;
			}
			button.disabled = true;
			request('jobs/' + jobId + '/' + action, payload).then(function () {
				window.location.reload();
			}).catch(function (error) {
				adminNotice(text(error.message) || window.ChidemoonAiAdmin.error);
				button.disabled = false;
			});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		if (!window.ChidemoonAiAdmin) {
			return;
		}
		document.querySelectorAll('form[data-chidemoon-ai-job]').forEach(initForm);
		document.querySelectorAll('[data-chidemoon-ai-review]').forEach(initReview);
		document.querySelectorAll('[data-chidemoon-ai-picker]').forEach(initPicker);
		document.querySelectorAll('[data-look-product-picker]').forEach(initLookPicker);
		document.querySelectorAll('[data-look-reference-picker]').forEach(initReferencePicker);
		initDiag();
	});

	function initReferencePicker(root) {
		var field = root.querySelector('[name="source_attachment_ids"]'), chosen = new Map();
		var list = root.querySelector('[data-reference-selected]'), status = root.querySelector('[data-reference-status]');
		function render() {
			field.value = Array.from(chosen.keys()).join(','); list.replaceChildren();
			chosen.forEach(function (image) {
				var item = document.createElement('div'); item.className = 'ch-ai-product-row';
				var thumb = document.createElement('img'); thumb.src = image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url; thumb.alt = '';
				var title = document.createElement('span'); title.textContent = image.title || image.filename;
				var remove = document.createElement('button'); remove.type = 'button'; remove.className = 'button'; remove.textContent = 'حذف';
				remove.setAttribute('aria-label', 'حذف عکس ' + title.textContent);
				remove.addEventListener('click', function () { chosen.delete(image.id); status.textContent = ''; render(); });
				item.append(thumb, title, remove); list.appendChild(item);
			});
		}
		root.querySelector('[data-reference-open]').addEventListener('click', function () {
			if (!window.wp || !wp.media) { status.textContent = 'انتخاب‌گر عکس آماده نشد؛ صفحه را تازه کن.'; return; }
			var frame = wp.media({ title: 'انتخاب حداکثر دو عکس مرجع', button: { text: 'استفاده از عکس‌ها' }, library: { type: 'image' }, multiple: true });
			frame.on('open', function () { chosen.forEach(function (image) { frame.state().get('selection').add(wp.media.attachment(image.id)); }); });
			frame.on('select', function () {
				var images = frame.state().get('selection').toJSON();
				if (images.length > 2) { status.textContent = 'حداکثر دو عکس انتخاب کن؛ انتخاب قبلی حفظ شد.'; return; }
				chosen.clear(); images.forEach(function (image) { if (image.type === 'image') chosen.set(image.id, image); });
				status.textContent = chosen.size ? chosen.size + ' عکس مرجع انتخاب شد.' : ''; render();
			});
			frame.open();
		});
	}

	function initLookPicker(root) {
		var search = root.querySelector('input[type="search"]'), field = root.querySelector('[name="product_ids"]');
		var results = root.querySelector('[data-product-results]'), selected = root.querySelector('[data-product-selected]');
		var status = root.querySelector('[data-product-status]'), choices = new Map(), timer, controller, sequence = 0;
		function row(product, remove) {
			var item = document.createElement('div'); item.className = 'ch-ai-product-row';
			if (product.image) { var image = document.createElement('img'); image.src = product.image; image.alt = ''; item.appendChild(image); }
			var name = document.createElement('span'); name.textContent = product.title; item.appendChild(name);
			var button = document.createElement('button'); button.type = 'button'; button.className = 'button';
			button.textContent = remove ? 'حذف' : (choices.has(product.id) ? 'اضافه شده' : 'افزودن');
			button.disabled = !remove && (choices.has(product.id) || choices.size >= 6);
			button.setAttribute('aria-label', (remove ? 'حذف ' : 'افزودن ') + product.title);
			button.addEventListener('click', function () {
				clearTimeout(timer); ++sequence; if (controller) controller.abort();
				if (remove) choices.delete(product.id); else if (choices.size < 6) choices.set(product.id, product);
				field.value = Array.from(choices.keys()).join(','); selected.replaceChildren();
				choices.forEach(function (p) { selected.appendChild(row(p, true)); });
				results.replaceChildren(); search.value = ''; status.textContent = choices.size + ' محصول انتخاب شده' + (choices.size === 6 ? '؛ به سقف انتخاب رسیدی.' : '');
			});
			item.appendChild(button); return item;
		}
		search.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
		search.addEventListener('input', function () {
			clearTimeout(timer); if (controller) controller.abort(); var requestId = ++sequence;
			var term = search.value.trim(); results.replaceChildren();
			if (term.length < 2) { status.textContent = ''; return; }
			status.textContent = 'در حال جست‌وجو…';
			timer = setTimeout(function () {
				controller = new AbortController();
				fetch(window.ChidemoonAiAdmin.root + 'products?search=' + encodeURIComponent(term), { credentials: 'same-origin', headers: { 'X-WP-Nonce': window.ChidemoonAiAdmin.nonce }, signal: controller.signal })
				.then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
				.then(function (products) { if (requestId !== sequence) return; status.textContent = products.length ? 'محصول موردنظر را اضافه کن.' : 'محصولی پیدا نشد؛ نام دیگری یا لینک محصول در چیدمون را امتحان کن.'; products.forEach(function (p) { results.appendChild(row(p, false)); }); })
				.catch(function (e) { if (e.name !== 'AbortError' && requestId === sequence) status.textContent = 'جست‌وجو انجام نشد؛ دوباره امتحان کن.'; });
			}, 300);
		});
	}

	function initPicker(button) {
		button.addEventListener('click', function () {
			var term = window.prompt(window.ChidemoonAiAdmin.pickProduct || 'Search:');
			if (term === null || term.trim().length < 2) {
				return;
			}
			var config = window.ChidemoonAiAdmin;
			fetch('/wp-json/chidemoon-core/v1/compare-products?search=' + encodeURIComponent(term.trim()), {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': config.nonce }
			}).then(function (response) { return response.json(); }).then(function (body) {
				var list = Array.isArray(body) ? body : [];
				if (!list.length) {
					window.alert(config.pickNone || 'No products found.');
					return;
				}
				var names = list.slice(0, 12).map(function (p) { return '#' + p.id + ' ' + (p.title || ''); }).join('\n');
				var chosen = window.prompt(names + '\n\nIDs (comma-separated):', String(list[0].id));
				if (chosen === null) {
					return;
				}
				var form = button.closest('form');
				var fieldName = button.getAttribute('data-chidemoon-ai-picker');
				var field = form ? form.elements[fieldName] : null;
				if (field) {
					field.value = chosen;
				}
			}).catch(function () {});
		});
	}

	function initDiag() {
		var testButton = document.getElementById('chidemoon-ai-test-connection');
		if (testButton) {
			testButton.addEventListener('click', function () {
				var output = document.getElementById('chidemoon-ai-test-result');
				output.textContent = window.ChidemoonAiAdmin.testing || 'Testing…';
				request('diag/test', {}).then(function (response) {
					output.textContent = (window.ChidemoonAiAdmin.testOk || 'OK') + ' ' + (response.text_model || '') + ' / ' + (response.image_model || '') + ' (' + (response.latency_ms || 0) + 'ms)';
				}).catch(function (error) {
					output.textContent = text(error.message) || window.ChidemoonAiAdmin.error;
				});
			});
		}
		var visionButton = document.getElementById('chidemoon-ai-test-vision');
		if (visionButton) {
			visionButton.addEventListener('click', function () {
				var output = document.getElementById('chidemoon-ai-vision-result');
				var field = document.getElementById('chidemoon-ai-vision-attachment');
				output.textContent = window.ChidemoonAiAdmin.testing || 'Testing…';
				request('diag/vision', { attachment_id: field ? Number(field.value) : 0 }).then(function (response) {
					output.textContent = (window.ChidemoonAiAdmin.testOk || 'OK') + ' ' + (response.vision_model || '');
				}).catch(function (error) {
					output.textContent = text(error.message) || window.ChidemoonAiAdmin.error;
				});
			});
		}
	}
}());
