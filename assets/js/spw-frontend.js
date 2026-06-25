(function() {
	'use strict';

	function pad(value) {
		value = parseInt(value, 10);
		if (isNaN(value) || value < 0) {
			value = 0;
		}
		return value < 10 ? '0' + value : String(value);
	}

	function updateTimer(timer) {
		var end = parseInt(timer.getAttribute('data-end'), 10);

		if (!end) {
			return;
		}

		var now = Math.floor(Date.now() / 1000);
		var remaining = end - now;

		if (remaining <= 0) {
			timer.style.display = 'none';
			return;
		}

		var days = Math.floor(remaining / 86400);
		var hours = Math.floor((remaining % 86400) / 3600);
		var minutes = Math.floor((remaining % 3600) / 60);
		var seconds = remaining % 60;

		var daysEl = timer.querySelector('.spw-days');
		var hoursEl = timer.querySelector('.spw-hours');
		var minutesEl = timer.querySelector('.spw-minutes');
		var secondsEl = timer.querySelector('.spw-seconds');

		if (daysEl) {
			daysEl.textContent = days;
		}

		if (hoursEl) {
			hoursEl.textContent = pad(hours);
		}

		if (minutesEl) {
			minutesEl.textContent = pad(minutes);
		}

		if (secondsEl) {
			secondsEl.textContent = pad(seconds);
		}
	}

	function initTimers() {
		var timers = document.querySelectorAll('.spw-timer-box');

		timers.forEach(function(timer) {
			updateTimer(timer);
		});
	}

	function initLoadMore() {
		document.addEventListener('click', function(e) {
			var button = e.target.closest('.spw-load-more');

			if (!button) {
				return;
			}

			e.preventDefault();

			var wrap = button.closest('.spw-products-wrap');

			if (!wrap || typeof spwFrontend === 'undefined') {
				return;
			}

			var page = parseInt(wrap.getAttribute('data-page'), 10) || 1;
			var maxPages = parseInt(wrap.getAttribute('data-max-pages'), 10) || 1;
			var nextPage = page + 1;

			if (nextPage > maxPages) {
				button.remove();
				return;
			}

			var formData = new FormData();
			formData.append('action', 'spw_load_more');
			formData.append('nonce', spwFrontend.nonce);
			formData.append('page', nextPage);
			formData.append('limit', wrap.getAttribute('data-limit') || 8);
			formData.append('orderby', wrap.getAttribute('data-orderby') || 'ending_soon');
			formData.append('category', wrap.getAttribute('data-category') || '');

			button.disabled = true;
			button.textContent = 'در حال بارگذاری...';

			fetch(spwFrontend.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData
			})
				.then(function(response) {
					return response.json();
				})
				.then(function(data) {
					if (!data || !data.success) {
						throw new Error('Ajax failed');
					}

					var grid = wrap.querySelector('.spw-products-grid');

					if (grid && data.data.html) {
						grid.insertAdjacentHTML('beforeend', data.data.html);
					}

					wrap.setAttribute('data-page', data.data.page);

					if (parseInt(data.data.page, 10) >= parseInt(data.data.max_pages, 10)) {
						button.remove();
					} else {
						button.disabled = false;
						button.textContent = 'نمایش محصولات بیشتر';
					}

					initTimers();
				})
				.catch(function() {
					button.disabled = false;
					button.textContent = 'تلاش دوباره';
				});
		});
	}

	document.addEventListener('DOMContentLoaded', function() {
		initTimers();
		initLoadMore();

		setInterval(initTimers, 1000);
	});
})();
