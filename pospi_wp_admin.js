(function($) {
	$(function() {
		// init formIO for our metabox fields
		var metaboxes = $('input[name=custom_post_type]').parent();

		// init form UI javascript
		metaboxes.closest('form').formio({
			setupRoutines : {
				"[data-fio-type='posttype_attachment']" : function(el) {
					FormIO.prototype.initAutoCompleteField.call(this, el, {
						preventDuplicates : true,
						resultsFormatter : function(item) {
							return "<li>" + item.name + " <sub>(<a target=\"_blank\" href=\"" + item.editUrl + "\">edit</a> | <a target=\"_blank\" href=\"" + item.viewUrl + "\">view</a>)</sub></li>";
						},
						tokenFormatter : function(item) {
							return "<li><p>" + item.name + " <sub>(<a target=\"_blank\" href=\"" + item.editUrl + "\">edit</a> | <a target=\"_blank\" href=\"" + item.viewUrl + "\">view</a>)</sub></p></li>";
						}
					});
				},
				"[data-fio-type='posttype_attachment_image']" : function(el) {
					FormIO.prototype.initAutoCompleteField.call(this, el, {
						preventDuplicates : true,
						resultsFormatter : function(item) {
							return "<li class=\"img\"><img src=\"" + item.thumbUrl + "\" />" + item.name + " <sub>(<a target=\"_blank\" href=\"" + item.editUrl + "\">edit</a> | <a target=\"_blank\" href=\"" + item.viewUrl + "\">view</a>)</sub></li>";
						},
						tokenFormatter : function(item) {
							return "<li class=\"img\"><div><img src=\"" + item.thumbUrl + "\" /></div><p>" + item.name + " <sub>(<a target=\"_blank\" href=\"" + item.editUrl + "\">edit</a> | <a target=\"_blank\" href=\"" + item.viewUrl + "\">view</a>)</sub></p></li>";
						}
					});
				},
				"[data-fio-type='posttype_link']" : function(el) {
					FormIO.prototype.initAutoCompleteField.call(this, el, {
						preventDuplicates : true,
						resultsFormatter : function(item) {
							return "<li>" + item.name + " <sub>(<a target=\"_blank\" href=\"" + item.editUrl + "\">edit</a> | <a target=\"_blank\" href=\"" + item.linkUrl + "\">open</a>)</sub></li>";
						},
						tokenFormatter : function(item) {
							return "<li><p>" + item.name + " <sub>(<a target=\"_blank\" href=\"" + item.editUrl + "\">edit</a> | <a target=\"_blank\" href=\"" + item.linkUrl + "\">open</a>)</sub></p></li>";
						}
					});
				}
			}
		});
	});
})(jQuery);
