(function () {
	'use strict';

	function text(value) {
		return typeof value === 'string' ? value : '';
	}

	function initFactsEditor() {
		var textarea = document.getElementById('_chidemoon_product_facts');
		if (!textarea || textarea.dataset.chidemoonEnhanced) return;
		textarea.dataset.chidemoonEnhanced = '1';
		// Hide raw textarea but keep it for form submit; show structured UI above it.
		textarea.style.display = 'none';
		var wrapper = document.createElement('div');
		wrapper.className = 'chidemoon-facts-editor';
		wrapper.style.cssText = 'margin:8px 0 12px;border:1px solid #c3c4c7;border-radius:6px;padding:10px;background:#fdfdfd';
		var help = document.createElement('p');
		help.style.cssText = 'margin:0 0 8px;font-size:12px;color:#555';
		help.textContent = 'ویژگی‌های ساختاریافته محصول — هر ردیف یک ویژگی قابل مقایسه است (مثلاً جنس: چوب راش). این داده در جدول مقایسه نمایش داده می‌شود.';
		wrapper.appendChild(help);
		var list = document.createElement('div');
		list.className = 'chidemoon-facts-list';
		wrapper.appendChild(list);
		var actions = document.createElement('div');
		actions.style.cssText = 'margin-top:8px;display:flex;gap:8px;align-items:center;';
		var addBtn = document.createElement('button');
		addBtn.type = 'button';
		addBtn.className = 'button';
		addBtn.textContent = '+ افزودن ویژگی';
		var rawBtn = document.createElement('button');
		rawBtn.type = 'button';
		rawBtn.className = 'button';
		rawBtn.textContent = 'نمایش JSON خام';
		rawBtn.style.marginLeft = '6px';
		actions.appendChild(addBtn);
		actions.appendChild(rawBtn);
		var status = document.createElement('span');
		status.style.cssText = 'font-size:11px;color:#666;margin-left:8px';
		actions.appendChild(status);
		wrapper.appendChild(actions);
		textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);

		function parseFacts() {
			var raw = (textarea.value || '').trim();
			if (!raw) return { state: 'empty', rows: [] };
			try {
				var data = JSON.parse(raw);
				if (Array.isArray(data)) {
					var arrayRows = data.map(function (item) {
						if (!item || typeof item !== 'object' || !('label' in item) || !('value' in item)) return null;
						var label = String(item.label || '').trim();
						var value = String(item.value || '').trim();
						return label && value ? { label: label, value: value } : null;
					});
					return arrayRows.every(Boolean) ? { state: 'supported', rows: arrayRows } : { state: 'invalid', rows: [] };
				}
				if (data && typeof data === 'object') {
					var keys = Object.keys(data);
					var objectRows = keys.map(function (key) {
						var value = data[key];
						if (value && typeof value === 'object' && 'label' in value && 'value' in value) {
							var nestedLabel = String(value.label || '').trim();
							var nestedValue = String(value.value || '').trim();
							return nestedLabel && nestedValue ? { label: nestedLabel, value: nestedValue } : null;
						}
						if (typeof value === 'string' || typeof value === 'number') {
							var label = String(key).trim();
							var fact = String(value).trim();
							return label && fact ? { label: label, value: fact } : null;
						}
						return null;
					});
					return objectRows.every(Boolean) ? { state: keys.length ? 'supported' : 'empty', rows: objectRows } : { state: 'invalid', rows: [] };
				}
			} catch (e) { }
			return { state: 'invalid', rows: [] };
		}

		function setStatus(message, isError) {
			status.textContent = message;
			status.style.color = isError ? '#d63638' : '#666';
		}

		function syncToTextarea(rows) {
			var filtered = rows.filter(function (row) { return row.label.trim() && row.value.trim(); });
			if (!filtered.length) {
				textarea.value = '';
				setStatus('— بدون ویژگی', false);
				return;
			}
			var payload = {};
			filtered.forEach(function (row) { payload[row.label.trim()] = row.value.trim(); });
			textarea.value = JSON.stringify(payload, null, 2);
			setStatus(filtered.length + ' ویژگی', false);
		}

		function render(rows) {
			list.innerHTML = '';
			if (!rows.length) {
				var empty = document.createElement('p');
				empty.style.cssText = 'margin:6px 0;color:#777;font-size:12px';
				empty.textContent = 'هنوز ویژگی‌ای ثبت نشده. افزودن را بزنید.';
				list.appendChild(empty);
			}
			rows.forEach(function (row, idx) {
				var rowEl = document.createElement('div');
				rowEl.style.cssText = 'display:flex;gap:6px;align-items:center;margin-bottom:6px;';
				var labelInput = document.createElement('input');
				labelInput.type = 'text';
				labelInput.placeholder = 'عنوان ویژگی (مثلاً ابعاد)';
				labelInput.value = row.label;
				labelInput.style.cssText = 'flex:1;';
				labelInput.className = 'regular-text';
				var valueInput = document.createElement('input');
				valueInput.type = 'text';
				valueInput.placeholder = 'مقدار (مثلاً 120×80 سانتی‌متر)';
				valueInput.value = row.value;
				valueInput.style.cssText = 'flex:1;';
				valueInput.className = 'regular-text';
				var del = document.createElement('button');
				del.type = 'button';
				del.className = 'button';
				del.textContent = '×';
				del.title = 'حذف';
				del.style.cssText = 'min-width:36px';
				labelInput.addEventListener('input', function () { rows[idx].label = labelInput.value; syncToTextarea(rows); });
				valueInput.addEventListener('input', function () { rows[idx].value = valueInput.value; syncToTextarea(rows); });
				del.addEventListener('click', function () { rows.splice(idx, 1); syncToTextarea(rows); render(rows); });
				rowEl.appendChild(labelInput);
				rowEl.appendChild(valueInput);
				rowEl.appendChild(del);
				list.appendChild(rowEl);
			});
		}

		function showInvalidFacts() {
			list.innerHTML = '<p style="margin:6px 0;color:#d63638;font-size:12px">JSON فعلی نامعتبر یا در این ویرایشگر پشتیبانی‌نشده است. برای جلوگیری از حذف داده، بدون تغییر نگه داشته شد.</p>';
			textarea.style.display = 'block';
			rawBtn.hidden = true;
			addBtn.disabled = true;
			setStatus('JSON را اصلاح کنید تا ویرایش ساختاریافته فعال شود', true);
		}

		function applyParsedFacts() {
			var parsed = parseFacts();
			if ('invalid' === parsed.state) {
				showInvalidFacts();
				return false;
			}
			rows = parsed.rows;
			textarea.style.display = 'none';
			rawBtn.hidden = false;
			addBtn.disabled = false;
			rawBtn.textContent = 'نمایش JSON خام';
			render(rows);
			setStatus(rows.length ? rows.length + ' ویژگی' : '— بدون ویژگی', false);
			return true;
		}

		var rows = [];
		applyParsedFacts();

		addBtn.addEventListener('click', function () {
			rows.push({ label: '', value: '' });
			render(rows);
			var inputs = list.querySelectorAll('input');
			if (inputs.length) inputs[inputs.length - 2].focus();
		});
		rawBtn.addEventListener('click', function () {
			textarea.style.display = textarea.style.display === 'none' ? 'block' : 'none';
			rawBtn.textContent = textarea.style.display === 'none' ? 'نمایش JSON خام' : 'مخفی کردن JSON';
			if (textarea.style.display !== 'none') textarea.focus();
		});
		textarea.addEventListener('change', applyParsedFacts);
	}

	document.addEventListener('DOMContentLoaded', function () {
		// Structured facts editor for comparison table
		try { initFactsEditor(); } catch (e) {}
		var button = document.getElementById('chidemoon_enrich_button');
		if (!button || !window.ChidemoonProductAdmin) {
			return;
		}
		button.addEventListener('click', function () {
			var config = window.ChidemoonProductAdmin;
			var output = document.getElementById('chidemoon_enrich_result');
			var webBox = document.getElementById('chidemoon_enrich_use_web');
			button.disabled = true;
			if (output) {
				output.textContent = 'Queueing…';
			}
			fetch(config.root + 'jobs/enrich', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce
				},
				body: JSON.stringify({
					product_id: Number(button.getAttribute('data-product-id')),
					use_source_url: true,
					use_web: webBox ? !!webBox.checked : true,
					instructions: 'Enrich this product with accurate, concise Persian copy.'
				})
			}).then(function (response) {
				return response.json().then(function (body) {
					if (!response.ok) {
						throw new Error(text(body.message) || config.error);
					}
					return body;
				});
			}).then(function (response) {
				var id = response && response.job ? response.job.id : '';
				if (output) {
					output.textContent = config.queued + (id ? ' #' + id : '');
				}
				button.disabled = false;
			}).catch(function (error) {
				if (output) {
					output.textContent = text(error.message) || config.error;
				}
				button.disabled = false;
			});
		});
	});
}());
