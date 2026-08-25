/**
 * Kalahamoon Shop-the-Look — frontend interaction.
 */
(function () {
	'use strict';

	function init() {
		document.querySelectorAll('.kalahamoon-shop-the-look').forEach(function (fig) {
			if (fig.dataset.kalahamoonStlReady === '1') return;
			fig.dataset.kalahamoonStlReady = '1';

			var dots = fig.querySelectorAll('.kalahamoon-stl-dot[aria-controls]');
			var tooltips = fig.querySelectorAll('.kalahamoon-stl-tooltip');
			var activeDot = null;

			function closeAll(restoreFocus) {
				var dotToFocus = activeDot;
				dots.forEach(function (d) { d.setAttribute('aria-expanded', 'false'); });
				tooltips.forEach(function (t) { t.hidden = true; });
				activeDot = null;
				if (restoreFocus && dotToFocus) dotToFocus.focus();
			}

			function openTooltip(dot, tooltip) {
				activeDot = dot;
				dot.setAttribute('aria-expanded', 'true');
				tooltip.hidden = false;
				positionTooltip(dot, tooltip, fig);

				var focusTarget = tooltip.querySelector('.kalahamoon-stl-tp-close, a, button');
				if (focusTarget) {
					setTimeout(function () { focusTarget.focus(); }, 30);
				}
			}

			dots.forEach(function (dot) {
				dot.addEventListener('click', function (e) {
					e.stopPropagation();
					var idx = dot.dataset.idx;
					var tooltip = fig.querySelector('.kalahamoon-stl-tooltip[data-idx="' + idx + '"]');
					var isOpen = dot.getAttribute('aria-expanded') === 'true';
					closeAll(false);
					if (!isOpen && tooltip) openTooltip(dot, tooltip);
				});

				dot.addEventListener('keydown', function (e) {
					if (e.key === 'Escape') closeAll(false);
				});
			});

			tooltips.forEach(function (tooltip) {
				var btn = tooltip.querySelector('.kalahamoon-stl-tp-close');
				if (btn) {
					btn.addEventListener('click', function (e) {
						e.stopPropagation();
						closeAll(true);
					});
				}

				tooltip.addEventListener('keydown', function (e) {
					if (e.key === 'Escape') {
						e.preventDefault();
						closeAll(true);
					}
				});
			});

			document.addEventListener('click', function (e) {
				if (!fig.contains(e.target)) closeAll(false);
			});
		});
	}

	function positionTooltip(dot, tooltip, container) {
		var wasHidden = tooltip.hidden;
		if (wasHidden) tooltip.hidden = false;

		var tW = tooltip.offsetWidth || 260;
		var tH = tooltip.offsetHeight || 220;
		var margin = 10;

		var cRect = container.getBoundingClientRect();
		var dRect = dot.getBoundingClientRect();

		var dotX = dRect.left - cRect.left + dRect.width / 2;
		var dotY = dRect.top - cRect.top + dRect.height / 2;

		var left = dotX + margin;
		var top = dotY + margin;

		if (left + tW > cRect.width - margin) {
			left = dotX - tW - margin;
		}

		left = Math.max(margin, Math.min(left, cRect.width - tW - margin));
		top = Math.max(margin, Math.min(top, cRect.height - tH - margin));

		tooltip.style.left = left + 'px';
		tooltip.style.top = top + 'px';

		if (wasHidden) tooltip.hidden = true;
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
