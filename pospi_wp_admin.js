(function($) {
	$(function() {
		// init formIO for our metabox fields
		var metaboxes = $('input[name=custom_post_type]').parent();

		// init form UI javascript
		metaboxes.closest('form').formio({
			setupRoutines : {
				"[data-fio-type='posttype_attachment']" : function(el) {
					FormIO.prototype.initAutoCompleteField.call(this, el, {
						resultsFormatter : function(item) {
							return "<li>" + item.name + " <sub>(<a href=\"" + item.editUrl + "\">edit</a> | <a href=\"" + item.viewUrl + "\">view</a>)</sub></li>";
						}
					});
				},
				"[data-fio-type='posttype_attachment_image']" : function(el) {
					FormIO.prototype.initAutoCompleteField.call(this, el, {
						resultsFormatter : function(item) {
							return "<li class=\"img\"><img src=\"" + item.thumbUrl + "\" />" + item.name + " <sub>(<a href=\"" + item.editUrl + "\">edit</a> | <a href=\"" + item.viewUrl + "\">view</a>)</sub></li>";
						}
					});
				},
				"[data-fio-type='posttype_link']" : function(el) {
					FormIO.prototype.initAutoCompleteField.call(this, el, {
						resultsFormatter : function(item) {
							return "<li>" + item.name + " <sub>(<a href=\"" + item.editUrl + "\">edit</a> | <a href=\"" + item.linkUrl + "\">open</a>)</sub></li>";
						}
					});
				}
			}
		});
	});
})(jQuery);
