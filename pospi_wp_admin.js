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

		//--------------------------------------------------------------------------
		// init form UI javascript

		function initUrlInput(el) {
			var link = el.prev(),
				baseHref = link.attr('href');

			link.attr('href', baseHref.replace('%s', el.val()));
			el.blur(function() {
				link.attr('href', baseHref.replace('%s', el.val()));
			});
		};

		metaboxes.closest('form').formio({
			setupRoutines : {
				// custom (or builtin) post type inputs
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
						},
						// refresh or create parallax image input viewports when added to the input list
						onAdd : function(item) {
							var that = this;
							setTimeout(function() {
								that.prev('.token-input-list').find('div').each(function(i, imgVp) {
									var vp;
									if (vp = $(imgVp).data('jcparallax-viewport')) {
										vp.refreshCoords();
									} else {
										initThumbParallax($(imgVp));
									}
								});
							}, 100);
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
				},
				"[data-fio-type='posttype_user']" : function(el) {
					FormIO.prototype.initAutoCompleteField.call(this, el, {
						preventDuplicates : true,
						resultsFormatter : function(item) {
							return "<li>" + item.name + " &lt;" + item.emailAddr + "&gt; <sub>(<a target=\"_blank\" href=\"" + item.editUrl + "\">edit</a>)</sub></li>";
						},
						tokenFormatter : function(item) {
							return "<li><p>" + item.name + " <sub>(<a target=\"_blank\" href=\"" + item.editUrl + "\">edit</a>)</sub></p></li>";
						}
					});
				},

				// additional useful inputs
				"[data-fio-type='facebook_user']" : initUrlInput,
				"[data-fio-type='twitter_user']" : initUrlInput
			}
		});

		// load parallax preview of image attachments
		initThumbParallax($('ul.token-input-list li.img div'));

		//--------------------------------------------------------------------------
		// custom taxonomy inputs for non-post pages

		function initTagBoxes()
		{
			tagBox.init();
			// save tags on post save/publish
			$('.tagsdiv').closest('form').submit(function(){
				$('div.tagsdiv', this).each(function() {
					tagBox.flushTags(this, false, 1);
				});
			});
		}

		if ( !$('#tagsdiv-post_tag').length ) {
			if ($('.tagsdiv').length) {
				initTagBoxes();
			} else {
				$('#side-sortables, #normal-sortables, #advanced-sortables').children('div.postbox').each(function(){
					if ( this.id.indexOf('tagsdiv-') !== 0 && $(this).hasClass('tagsdiv') ) {
						initTagBoxes();
						return false;
					}
				});
			}
		}
	});

	//--------------------------------------------------------------------------
	// helpers
	//--------------------------------------------------------------------------

	function initThumbParallax(els) {
		els.jcparallax({
			layerSelector: 'img',
			// y-axis movement only
			inputHandler: function(el, evt) {
				var yPos = evt.pageY - this.viewport.offsetY;

				this.updateLastSamplePos(0, yPos / this.viewport.sizeY);
			},
			inputEvent: 'mousemove',
			animHandler: 'position'
		});
	}

})(jQuery);
