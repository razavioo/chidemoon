(function () {
	'use strict';

	function text(value) {
		return typeof value === 'string' ? value : '';
	}

	document.addEventListener('DOMContentLoaded', function () {
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
