(function($) {
	$(function() {
		// init formIO for our metabox fields
		var metaboxes = $('input[name=custom_post_type]').parent();

		metaboxes.addClass('formio');
		metaboxes.closest('form').formio();
	});
})(jQuery);
