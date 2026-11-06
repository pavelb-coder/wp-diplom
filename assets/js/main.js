/**
 * WP Diplom — скрипты темы.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var toggle = document.querySelector('.menu-toggle');
		var nav = document.querySelector('.site-nav');

		if (toggle && nav) {
			toggle.addEventListener('click', function () {
				var isOpen = nav.classList.toggle('is-open');
				toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			});

			// Закрытие меню по Esc.
			document.addEventListener('keydown', function (event) {
				if (event.key === 'Escape' && nav.classList.contains('is-open')) {
					nav.classList.remove('is-open');
					toggle.setAttribute('aria-expanded', 'false');
					toggle.focus();
				}
			});
		}

		// Плавное появление карточек.
		var cards = document.querySelectorAll('.card');
		if (!cards.length || !('IntersectionObserver' in window)) {
			return;
		}

		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					entry.target.style.opacity = '1';
					entry.target.style.transform = 'none';
					observer.unobserve(entry.target);
				}
			});
		}, { threshold: 0.1 });

		Array.prototype.forEach.call(cards, function (card) {
			card.style.opacity = '0';
			card.style.transform = 'translateY(12px)';
			card.style.transition = 'opacity .4s ease, transform .4s ease';
			observer.observe(card);
		});
	});
})();
