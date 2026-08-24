(function () {
	'use strict';

	function text(value) {
		return typeof value === 'string' ? value : '';
	}

	function renderSources(container, sources) {
		var list = document.createElement('ul');
		list.className = 'chidemoon-ai-assistant__sources';

		sources.forEach(function (source) {
			if (!source || typeof source !== 'object') {
				return;
			}
			var url;
			try {
				url = new URL(text(source.url), window.location.origin);
			} catch (error) {
				return;
			}
			if (url.origin !== window.location.origin) {
				return;
			}

			var item = document.createElement('li');
			var link = document.createElement('a');
			link.href = url.href;
			link.textContent = text(source.title) || url.pathname;
			item.appendChild(link);
			if (text(source.excerpt)) {
				var excerpt = document.createElement('p');
				excerpt.textContent = text(source.excerpt);
				item.appendChild(excerpt);
			}
			list.appendChild(item);
		});

		container.appendChild(list);
	}

	function setMessage(container, message) {
		container.replaceChildren();
		var paragraph = document.createElement('p');
		paragraph.textContent = message;
		container.appendChild(paragraph);
	}

	function init(widget) {
		var form = widget.querySelector('form');
		var field = widget.querySelector('textarea[name="question"]');
		var result = widget.querySelector('.chidemoon-ai-assistant__result');
		if (!form || !field || !result || !window.ChidemoonAiAssistant) {
			return;
		}

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			var question = field.value.trim();
			if (question.length < 3) {
				setMessage(result, 'Please enter a longer question.');
				return;
			}

			setMessage(result, 'Searching published Chidemoon sources…');
			fetch(window.ChidemoonAiAssistant.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ question: question })
			}).then(function (response) {
				return response.json().then(function (payload) {
					if (!response.ok) {
						throw new Error(text(payload.message) || 'request_failed');
					}
					return payload;
				});
			}).then(function (payload) {
				result.replaceChildren();
				var answer = document.createElement('p');
				answer.textContent = text(payload.answer);
				result.appendChild(answer);
				renderSources(result, Array.isArray(payload.sources) ? payload.sources : []);
			}).catch(function () {
				setMessage(result, text(window.ChidemoonAiAssistant.error) || 'The assistant is unavailable.');
			});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-chidemoon-ai-assistant]').forEach(init);
	});
}());
