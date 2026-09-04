(function (wp) {
	'use strict';

	if (!wp || !wp.plugins || !wp.editPost) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar = wp.editPost.PluginSidebar;
	var Button = wp.components.Button;
	var SelectControl = wp.components.SelectControl;
	var TextareaControl = wp.components.TextareaControl;
	var __ = wp.i18n.__;

	function Sidebar() {
		var kindState = useState('product_description');
		var kind = kindState[0];
		var setKind = kindState[1];
		var toneState = useState('formal');
		var tone = toneState[0];
		var setTone = toneState[1];
		var statusState = useState('');
		var status = statusState[0];
		var setStatus = statusState[1];
		var instructionState = useState('');
		var instructions = instructionState[0];
		var setInstructions = instructionState[1];

		function queue() {
			var config = window.ChidemoonAiAdmin;
			if (!config) {
				return;
			}
			var postId = wp.data.select('core/editor') ? wp.data.select('core/editor').getCurrentPostId() : 0;
			setStatus(__('Queueing…', 'chidemoon-ai'));
			fetch(config.root + 'jobs/text', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce
				},
				body: JSON.stringify({
					kind: kind,
					tone: tone,
					length: 'medium',
					lang: 'fa',
					target_post_id: postId || undefined,
					source_post_ids: postId ? [postId] : [],
					instructions: instructions || kind
				})
			}).then(function (response) {
				return response.json().then(function (body) {
					if (!response.ok) {
						throw new Error(body.message || 'error');
					}
					return body;
				});
			}).then(function (response) {
				var id = response && response.job ? response.job.id : '';
				setStatus((__('Queued', 'chidemoon-ai')) + (id ? ' #' + id : ''));
			}).catch(function (error) {
				setStatus(error.message || 'error');
			});
		}

		return el(PluginSidebar, { name: 'chidemoon-ai-sidebar', title: __('Chidemoon AI', 'chidemoon-ai') },
			el('div', { style: { padding: '16px' } },
				el(SelectControl, {
					label: __('Task', 'chidemoon-ai'),
					value: kind,
					options: [
						{ label: __('Product description', 'chidemoon-ai'), value: 'product_description' },
						{ label: __('FAQ', 'chidemoon-ai'), value: 'faq' },
						{ label: __('Pros and cons', 'chidemoon-ai'), value: 'pros_cons' },
						{ label: __('Buying guide', 'chidemoon-ai'), value: 'buying_guide' },
						{ label: __('SEO draft', 'chidemoon-ai'), value: 'seo_draft' },
						{ label: __('Shop the look caption', 'chidemoon-ai'), value: 'shop_the_look_caption' }
					],
					onChange: setKind
				}),
				el(SelectControl, {
					label: __('Tone', 'chidemoon-ai'),
					value: tone,
					options: [
						{ label: __('Formal', 'chidemoon-ai'), value: 'formal' },
						{ label: __('Friendly', 'chidemoon-ai'), value: 'friendly' },
						{ label: __('Expert', 'chidemoon-ai'), value: 'expert' }
					],
					onChange: setTone
				}),
				el(TextareaControl, { label: __('Request', 'chidemoon-ai'), value: instructions, onChange: setInstructions, rows: 4 }),
				el(Button, { variant: 'primary', onClick: queue }, __('Queue AI job', 'chidemoon-ai')),
				status ? el('p', null, status) : null
			)
		);
	}

	registerPlugin('chidemoon-ai-sidebar', { render: Sidebar });
}(window.wp));
