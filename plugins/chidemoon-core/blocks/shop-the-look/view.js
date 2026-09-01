(function () {
	'use strict';

	function init(root) {
		if (root.dataset.chidemoonReady === '1') return;
		root.dataset.chidemoonReady = '1';
		root.classList.add('is-enhanced');
		var hotspots = root.querySelectorAll('.chidemoon-shop-the-look__hotspot');
		var tooltips = root.querySelectorAll('.chidemoon-shop-the-look__tooltip');
		var active = null;
		var focusManaged = false;

		function tooltipFor(spot) {
			return document.getElementById(spot.dataset.tooltip);
		}

		function place(spot, tooltip) {
			tooltip.classList.remove('chidemoon-shop-the-look__tooltip--mobile');
			tooltip.style.left = '';
			tooltip.style.top = '';
			if (window.matchMedia('(max-width: 640px)').matches) {
				tooltip.classList.add('chidemoon-shop-the-look__tooltip--mobile');
				return;
			}
			var canvas = root.querySelector('.chidemoon-shop-the-look__canvas');
			if (!canvas) return;
			var canvasRect = canvas.getBoundingClientRect();
			var spotRect = spot.getBoundingClientRect();
			var width = tooltip.offsetWidth || 288;
			var height = tooltip.offsetHeight || 240;
			var left = spotRect.left - canvasRect.left + (spotRect.width / 2) - (width / 2);
			var top = spotRect.top - canvasRect.top + spotRect.height + 12;
			if (top + height > canvasRect.height - 12) top = spotRect.top - canvasRect.top - height - 12;
			tooltip.style.left = Math.max(12, Math.min(left, canvasRect.width - width - 12)) + 'px';
			tooltip.style.top = Math.max(12, top) + 'px';
		}

		function closeAll(restore) {
			var previous = active;
			hotspots.forEach(function (spot) { spot.setAttribute('aria-expanded', 'false'); });
			tooltips.forEach(function (tooltip) {
				tooltip.hidden = true;
				tooltip.classList.remove('chidemoon-shop-the-look__tooltip--mobile');
				tooltip.style.left = '';
				tooltip.style.top = '';
			});
			active = null;
			if (restore && previous && focusManaged) previous.focus();
			focusManaged = false;
		}

		function open(spot, tooltip, moveFocus) {
			closeAll(false);
			spot.setAttribute('aria-expanded', 'true');
			tooltip.hidden = false;
			active = spot;
			place(spot, tooltip);
			focusManaged = Boolean(moveFocus);
			if (moveFocus) {
				var focusTarget = tooltip.querySelector('.chidemoon-shop-the-look__close, a');
				if (focusTarget) focusTarget.focus();
			}
		}

		hotspots.forEach(function (spot) {
			spot.addEventListener('click', function (event) {
				event.stopPropagation();
				var tooltip = tooltipFor(spot);
				if (!tooltip) return;
				if (active === spot) closeAll(true); else open(spot, tooltip, true);
			});
			spot.addEventListener('mouseenter', function () {
				if (!window.matchMedia('(hover: hover)').matches || active === spot) return;
				var tooltip = tooltipFor(spot);
				if (tooltip) open(spot, tooltip, false);
			});
		});

		tooltips.forEach(function (tooltip) {
			var close = tooltip.querySelector('.chidemoon-shop-the-look__close');
			if (close) close.addEventListener('click', function () { closeAll(true); });
			tooltip.addEventListener('mouseleave', function () {
				if (window.matchMedia('(hover: hover)').matches && !focusManaged) closeAll(false);
			});
		});

		root.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && active) {
				event.preventDefault();
				closeAll(true);
			}
		});
		document.addEventListener('click', function (event) {
			if (!root.contains(event.target)) closeAll(false);
		});
		window.addEventListener('resize', function () {
			if (active) {
				var tooltip = tooltipFor(active);
				if (tooltip) place(active, tooltip);
			}
		});
	}

	function boot() {
		document.querySelectorAll('.chidemoon-shop-the-look').forEach(init);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
