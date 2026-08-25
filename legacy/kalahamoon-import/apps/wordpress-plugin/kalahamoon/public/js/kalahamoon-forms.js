/**
 * Kalahamoon — front-end handler for lead-capture and price-alert forms.
 * Vanilla JS, no dependencies. Submits JSON to the plugin REST namespace.
 *
 * Markup contract:
 *   <form class="kalahamoon-form" data-kalahamoon-form="lead|price-alert"
 *         data-success="..." data-error="...">
 *     ...fields with name="name|email|phoneNumber|message|productId|website"...
 *     <div class="kalahamoon-form__status" role="status" aria-live="polite"></div>
 *   </form>
 */
(function () {
	'use strict';

	var cfg = window.kalahamoonForms || window.kalahamoonConfig || {};
	var REST = cfg.restUrl || '';
	var NONCE = cfg.nonce || '';

	var ENDPOINTS = {
		'lead': 'leads',
		'price-alert': 'price-alerts'
	};

	function fieldValue(form, name) {
		var node = form.querySelector('[name="' + name + '"]');
		return node ? String(node.value || '').trim() : '';
	}

	function fieldChecked(form, name) {
		var node = form.querySelector('[name="' + name + '"]');
		return !!(node && node.checked);
	}

	function setStatus(form, message, kind) {
		var box = form.querySelector('.kalahamoon-form__status');
		if (!box) return;
		box.textContent = message;
		box.className = 'kalahamoon-form__status' + (kind ? ' is-' + kind : '');
	}

	function submit(form) {
		if (form.dataset.kalahamoonSubmitting === '1') return;

		var type = form.getAttribute('data-kalahamoon-form');
		var path = ENDPOINTS[type];
		if (!path || !REST) return;

		var payload = {
			name: fieldValue(form, 'name'),
			email: fieldValue(form, 'email'),
			phoneNumber: fieldValue(form, 'phoneNumber'),
			subject: fieldValue(form, 'subject'),
			message: fieldValue(form, 'message'),
			productId: fieldValue(form, 'productId'),
			targetPrice: fieldValue(form, 'targetPrice'),
			intent: form.getAttribute('data-intent') || 'contact',
			consent: fieldChecked(form, 'consent'),
			consentVersion: form.getAttribute('data-consent-version') || '1',
			sourceRef: window.location.href,
			website: fieldValue(form, 'website') // honeypot
		};

		var btn = form.querySelector('button[type="submit"], input[type="submit"]');
		form.dataset.kalahamoonSubmitting = '1';
		form.setAttribute('aria-busy', 'true');
		if (btn) btn.disabled = true;
		setStatus(form, form.getAttribute('data-sending') || '…', 'pending');

		fetch(REST + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
			body: JSON.stringify(payload)
		}).then(function (res) {
			return res.json().then(function (data) { return { ok: res.ok, data: data }; });
		}).then(function (r) {
			if (r.ok) {
				var success = form.getAttribute('data-success') || 'OK';
				var requestId = r.data && typeof r.data.requestId === 'string' ? r.data.requestId.trim() : '';
				var referenceLabel = form.getAttribute('data-reference-label') || '';
				if (requestId && referenceLabel.indexOf('%s') !== -1) {
					success += ' ' + referenceLabel.replace('%s', requestId);
				}
				setStatus(form, success, 'success');
				form.reset();
			} else {
				setStatus(form, (r.data && r.data.message) || form.getAttribute('data-error') || 'Error', 'error');
			}
		}).catch(function () {
			setStatus(form, form.getAttribute('data-error') || 'Error', 'error');
		}).finally(function () {
			if (btn) btn.disabled = false;
			delete form.dataset.kalahamoonSubmitting;
			form.removeAttribute('aria-busy');
		});
	}

	function onSubmit(e) {
		var form = e.target.closest ? e.target.closest('form[data-kalahamoon-form]') : null;
		if (!form) return;
		e.preventDefault();
		submit(form);
	}

	document.addEventListener('submit', onSubmit, false);
})();
