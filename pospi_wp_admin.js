(function($) {
	$(function() {
		// find our metaboxs
		var metaboxes = $('input[name=custom_post_type]').parent();

		// add img class to formIO image input parents to allow separate styling of actual tokeninput input
		$('li.img.token-input-token').closest('token-input-list').addClass('img');

		// override form field name retriever to use custom_meta instead of #post (which it has to be in wordpress)
		FormIO.prototype.getFieldId = function(fldname) {
			return 'custom_meta_' + fldname.replace(/\[/g, '_').replace(/\]/g, '');
		};
		FormIO.prototype.getFieldName = function(fldId) {
			return fldId.replace(new RegExp('^custom_meta_'), '');
		};

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

		// load parallax preview of image attachments
		initThumbParallax($('ul.token-input-list li.img img'), $('ul.token-input-list li.img div'));
	});

	//--------------------------------------------------------------------------
	// helpers
	//--------------------------------------------------------------------------

	function initThumbParallax(els, mouseports) {
		els.each(function(i) {
			$(this).parallax({
				xparallax: false,
				yparallax: -1,
				mouseport: mouseports.get(i)
			});
		});
	}

})(jQuery);
