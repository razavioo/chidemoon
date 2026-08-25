/**
 * Kalahamoon — Lightweight touch-aware product carousel (~3KB)
 * Vanilla JS. No dependencies.
 * Uses CSS scroll-snap. RTL-aware swipe direction.
 */
(function () {
	'use strict';

	var SWIPE_THRESHOLD = 40;
	var AUTOPLAY_INTERVAL = 4000;

	function KalahamoonCarousel(root) {
		this.root = root;
		this.track = root.querySelector('.kalahamoon-carousel__track');
		if (!this.track) return;

		this.slides = Array.from(this.track.children);
		if (this.slides.length === 0) return;

		this.isRTL = false;
		this.autoplay = root.dataset.autoplay === 'true';
		this.showDots = root.dataset.dots !== 'false';
		this.showArrows = root.dataset.arrows !== 'false';
		this.prevLabel = root.dataset.prevLabel || 'Previous slide';
		this.nextLabel = root.dataset.nextLabel || 'Next slide';
		this.slideLabel = root.dataset.slideLabel || 'Go to slide';
		this.currentIndex = 0;
		this.autoplayTimer = null;
		this.touchStartX = 0;
		this.touchStartY = 0;
		this.isSwiping = false;

		// Single-slide carousels have no meaningful navigation; reduce to a
		// plain card, disable autoplay, and skip event wiring so there is no
		// infinite autoplay timer spinning in the background.
		if (this.slides.length < 2) {
			root.classList.add('kalahamoon-carousel--single');
			this.showArrows = false;
			this.showDots = false;
			this.autoplay = false;
			this.setupTrack();
			return;
		}

		// Respect prefers-reduced-motion: no autoplay, instant scroll.
		if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			this.autoplay = false;
		}

		this.setupTrack();
		if (this.showArrows) this.createArrows();
		if (this.showDots) this.createDots();
		this.bindEvents();
		if (this.autoplay) this.startAutoplay();
	}

	KalahamoonCarousel.prototype.setupTrack = function () {
		this.track.style.display = 'flex';
		this.track.style.overflowX = 'auto';
		this.track.style.scrollSnapType = 'x mandatory';
		this.track.style.scrollBehavior = 'smooth';
		this.track.style.webkitOverflowScrolling = 'touch';
		this.track.style.scrollbarWidth = 'none';
		this.track.style.msOverflowStyle = 'none';

		for (var i = 0; i < this.slides.length; i++) {
			this.slides[i].style.flexShrink = '0';
			this.slides[i].style.scrollSnapAlign = 'start';
		}
	};

	KalahamoonCarousel.prototype.createArrows = function () {
		var self = this;
		var prevLabel = this.isRTL ? '\u203A' : '\u2039'; // single angle quotes
		var nextLabel = this.isRTL ? '\u2039' : '\u203A';

		this.prevBtn = this.createButton('kalahamoon-carousel__arrow kalahamoon-carousel__arrow--prev', prevLabel, this.prevLabel);
		this.nextBtn = this.createButton('kalahamoon-carousel__arrow kalahamoon-carousel__arrow--next', nextLabel, this.nextLabel);

		this.prevBtn.addEventListener('click', function () { self.go(-1); });
		this.nextBtn.addEventListener('click', function () { self.go(1); });

		this.root.appendChild(this.prevBtn);
		this.root.appendChild(this.nextBtn);
	};

	KalahamoonCarousel.prototype.createButton = function (className, text, ariaLabel) {
		var btn = document.createElement('button');
		btn.className = className;
		btn.setAttribute('type', 'button');
		btn.setAttribute('aria-label', ariaLabel || text);
		btn.textContent = text;
		return btn;
	};

	KalahamoonCarousel.prototype.createDots = function () {
		var self = this;
		this.dotsContainer = document.createElement('div');
		this.dotsContainer.className = 'kalahamoon-carousel__dots';
		this.dots = [];

		for (var i = 0; i < this.slides.length; i++) {
			var dot = document.createElement('button');
			dot.className = 'kalahamoon-carousel__dot';
			dot.setAttribute('type', 'button');
			dot.setAttribute('aria-label', this.slideLabel + ' ' + (i + 1));
			dot.dataset.index = i;
			dot.addEventListener('click', function () {
				self.goTo(parseInt(this.dataset.index, 10));
			});
			this.dotsContainer.appendChild(dot);
			this.dots.push(dot);
		}

		this.root.appendChild(this.dotsContainer);
		this.updateDots();
	};

	KalahamoonCarousel.prototype.updateDots = function () {
		if (!this.dots) return;
		for (var i = 0; i < this.dots.length; i++) {
			this.dots[i].classList.toggle('is-active', i === this.currentIndex);
		}
	};

	KalahamoonCarousel.prototype.go = function (direction) {
		// In RTL, visual "next" (left arrow) means incrementing index
		var effectiveDir = this.isRTL ? -direction : direction;
		var next = this.currentIndex + effectiveDir;
		this.goTo(next);
	};

	KalahamoonCarousel.prototype.goTo = function (index) {
		if (index < 0) index = this.slides.length - 1;
		if (index >= this.slides.length) index = 0;
		this.currentIndex = index;

		this.slides[index].scrollIntoView({
			behavior: 'smooth',
			block: 'nearest',
			inline: 'start'
		});

		this.updateDots();
		if (this.autoplay) this.resetAutoplay();
	};

	KalahamoonCarousel.prototype.bindEvents = function () {
		var self = this;

		// Touch events
		this.track.addEventListener('touchstart', function (e) {
			self.touchStartX = e.touches[0].clientX;
			self.touchStartY = e.touches[0].clientY;
			self.isSwiping = false;
			self.pauseAutoplay();
		}, { passive: true });

		this.track.addEventListener('touchmove', function (e) {
			var dx = e.touches[0].clientX - self.touchStartX;
			var dy = e.touches[0].clientY - self.touchStartY;
			// Only consider horizontal swipes
			if (Math.abs(dx) > Math.abs(dy)) {
				self.isSwiping = true;
			}
		}, { passive: true });

		this.track.addEventListener('touchend', function (e) {
			if (!self.isSwiping) {
				self.resumeAutoplay();
				return;
			}
			var dx = e.changedTouches[0].clientX - self.touchStartX;
			if (Math.abs(dx) > SWIPE_THRESHOLD) {
				// In RTL, swipe-right means "go to previous" (same as LTR swipe-left)
				var direction = dx < 0 ? 1 : -1;
				if (self.isRTL) direction = -direction;
				self.go(direction);
			}
			self.resumeAutoplay();
		}, { passive: true });

		// Scroll-based index detection (for scroll-snap driven navigation)
		var scrollTimeout;
		this.track.addEventListener('scroll', function () {
			clearTimeout(scrollTimeout);
			scrollTimeout = setTimeout(function () {
				self.detectCurrentSlide();
			}, 100);
		}, { passive: true });

		// Pause autoplay on hover
		this.root.addEventListener('mouseenter', function () { self.pauseAutoplay(); });
		this.root.addEventListener('mouseleave', function () { self.resumeAutoplay(); });
	};

	KalahamoonCarousel.prototype.detectCurrentSlide = function () {
		var trackRect = this.track.getBoundingClientRect();
		var closest = 0;
		var minDist = Infinity;

		for (var i = 0; i < this.slides.length; i++) {
			var slideRect = this.slides[i].getBoundingClientRect();
			var dist = Math.abs(slideRect.left - trackRect.left);
			if (dist < minDist) {
				minDist = dist;
				closest = i;
			}
		}

		if (closest !== this.currentIndex) {
			this.currentIndex = closest;
			this.updateDots();
		}
	};

	KalahamoonCarousel.prototype.startAutoplay = function () {
		var self = this;
		this.autoplayTimer = setInterval(function () {
			self.go(1);
		}, AUTOPLAY_INTERVAL);
	};

	KalahamoonCarousel.prototype.pauseAutoplay = function () {
		if (this.autoplayTimer) {
			clearInterval(this.autoplayTimer);
			this.autoplayTimer = null;
		}
	};

	KalahamoonCarousel.prototype.resumeAutoplay = function () {
		if (this.autoplay && !this.autoplayTimer) {
			this.startAutoplay();
		}
	};

	KalahamoonCarousel.prototype.resetAutoplay = function () {
		this.pauseAutoplay();
		this.resumeAutoplay();
	};

	/* ── Lazy initialization with IntersectionObserver ── */
	function initCarousels() {
		var carousels = document.querySelectorAll('.kalahamoon-carousel');
		if (carousels.length === 0) return;

		if ('IntersectionObserver' in window) {
			var observer = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						new KalahamoonCarousel(entry.target);
						observer.unobserve(entry.target);
					}
				});
			}, { rootMargin: '200px' });

			carousels.forEach(function (el) { observer.observe(el); });
		} else {
			// Fallback: initialize immediately
			carousels.forEach(function (el) { new KalahamoonCarousel(el); });
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCarousels);
	} else {
		initCarousels();
	}
})();
