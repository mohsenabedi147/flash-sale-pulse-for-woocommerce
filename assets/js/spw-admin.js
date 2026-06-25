jQuery(function($) {
	'use strict';

	$('.spw-tab-link').on('click', function(e) {
		e.preventDefault();

		var target = $(this).attr('href');

		$('.spw-tab-link').removeClass('is-active');
		$('.spw-tab-content').removeClass('is-active');

		$(this).addClass('is-active');
		$(target).addClass('is-active');
	});
});
