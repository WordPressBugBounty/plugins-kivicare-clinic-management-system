(function ($) {
	'use strict';
	$(document).ready(() => {
		$(".iq-notice .notice-dismiss").on("click", function (event) {
			let key = $('.iq-notice #iq-notice-id').val();
			let nounce = $('.iq-notice #iq-notice-nounce').val();
			$.get(window.ajaxurl, {
				action: "iq_dismiss_notice",
				nounce,
				key

			});
		});
	});
})(jQuery);
