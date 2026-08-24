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
		var target = form.elements.target_post_id.value;
		var payload = {};
		if (target) {
			payload.target_post_id = Number(target);
		}
		if (type === 'text') {
			payload.kind = form.elements.kind.value;
			payload.source_post_ids = ids(form.elements.source_post_ids.value);
			payload.instructions = form.elements.instructions.value;
		} else if (type === 'comparison') {
			payload.product_ids = ids(form.elements.product_ids.value);
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
	});
}());
