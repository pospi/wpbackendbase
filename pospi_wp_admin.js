(function($) {
	// extend builtin form init routines
	$.extend(true, FormIO.prototype.options.setupRoutines,
	{
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
		"[data-fio-type='plupload']" : function(el) {
			initUploaderControl(el);
		},

		".row.external-link" : function(el) {
			bindLinkDialog(el);
		},

		// additional useful inputs
		"[data-fio-type='facebook_user']" : initUrlInput,
		"[data-fio-type='youtube_user']" : initUrlInput,
		"[data-fio-type='twitter_user']" : initUrlInput
	});

	// override form field name retriever to use custom_meta instead of #post or other admin form IDs (which it has to be in wordpress)
	var oldGetFieldId = FormIO.prototype.getFieldId;
	FormIO.prototype.getFieldId = function(fldname) {
		var id = this.elements.get(0).tagName.toLowerCase() == 'form' ? this.elements.attr('id') : this.elements.closest('form').attr('id');
		if (id == 'post' || id == 'media-single-form' || id == 'file-form' || id == 'addlink' || id == 'editlink' || id == 'createuser' || id == 'your-profile') {
			return 'custom_meta_' + fldname.replace(/\[/g, '_').replace(/\]/g, '');
		}
		return oldGetFieldId.call(this, fldname);
	};
	var oldGetFieldName = FormIO.prototype.getFieldName;
	FormIO.prototype.getFieldName = function(fldId) {
		var id = this.elements.get(0).tagName.toLowerCase() == 'form' ? this.elements.attr('id') : this.elements.closest('form').attr('id');
		if (id == 'post' || id == 'media-single-form' || id == 'file-form' || id == 'addlink' || id == 'editlink' || id == 'createuser' || id == 'your-profile') {
			return 'custom_meta[' + fldId.replace(new RegExp('^custom_meta_'), '')
						.replace(/_(\d+)_/g, '][$1][') + ']';
		}
		return oldGetFieldName.call(this, fldId);
	};

	// override the form field submit action to indicate an error in the WP UI instead of failing the submission silently
	var oldOnSubmit = FormIO.prototype.onSubmit;
	FormIO.prototype.onSubmit = function(e)
	{
		// ignore validation if draft
		if (SUBMIT_BTN == 'save-post') {
			return;
		}

		var ok = oldOnSubmit.call(this);

		// remove notifications from previous run
		$('#wpbody-content > .wrap').find('.formio-notifications').remove();

		if (!ok) {
			// reset the WP UI to its pre-submission state
			$('#publish').removeClass('button-primary-disabled');
			$('#save-post').removeClass('button-disabled');
			$('#publishing-action .spinner').hide();
			$('#ajax-loading').hide();

			// add notifications of errors
			var messages = {},
				messageStr = '',
				that = this;
			$.each(this.failedValidators, function(field, validator) {
				var el = $('#' + field);
				messages[that.getReadableFieldName(el) + " failed validation."] = true;     // :TODO: validator messages
			});
			$.each(messages, function(msg, v) {
				messageStr += "<div class=\"error formio-notifications\"><p>" + msg + "</p></div>";
			});
			var notifications = $(messageStr);

			$('#wpbody-content > .wrap').prepend(notifications);
		}

		return ok;
	}

	//--------------------------------------------------------------------------
	// init helpers

	function initUrlInput(el) {
		var link = el.prev(),
			baseHref = link.attr('href');

		link.attr('href', baseHref.replace('%s', el.val()));
		el.blur(function() {
			link.attr('href', baseHref.replace('%s', el.val()));
		});
	};

	function initPostBoxes(metaboxes) {
		var postForm, postBoxes;

		if (metaboxes.get(0).tagName.toLowerCase() == 'form') {
			postBoxes = metaboxes;
			postForm = null;
		} else {
			postForm = metaboxes.closest('form');
			postBoxes = postForm.find('.postbox.formio .inside');
		}

		postBoxes.formio();

		if (postForm) {
			postBoxes.formio('setupForm', postForm);
		}
	}

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

	function initThumbParallax(els)
	{
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

	//--------------------------------------------------------------------------
	// binding for Wordpress plUpload inputs
	//--------------------------------------------------------------------------

	function initUploaderControl(el)
	{
		// override uploader defaults for this particular field
		var uploader,
			uploaderId = el.attr('id'),
			galleryLink = $('.pb-media-gallery', el),
			nonce = $( '#nonce-upload-images_' + uploaderId ).val(),
			postId = $('#post_ID').val(),
			uploaderConfig = $.extend({}, pbase_plupload_config, {
				container    : uploaderId + '-container',
				browse_button: uploaderId + '-browse-button',
				drop_element : uploaderId + '-dragdrop'
			});

		// Add POST variables to send with the uploader requests
		uploaderConfig['multipart_params'] = {
			action  : 'pbase_upload_input_handle',
			pt: el.data('posttype'),
			form: el.data('metabox'),
			field: el.data('field'),
			post_ID : postId,
			_wpnonce: nonce
		};

		// Create new uploader
		uploader = new plupload.Uploader(uploaderConfig);
		uploader.init();
		uploader.bind('FilesAdded', handleUpload);
		uploader.bind('UploadProgress', handleUploadProgress);
		uploader.bind('FileUploaded', handleUploadComplete);

		// handle sorting
		el.find('ul.uploaded-images').sortable({
			items : 'li'
		});

		// bind an event to manage removals
		el.on('click', '.img-del', function(e) {
			e.preventDefault();
			$(this).closest('li').fadeOut('slow', function() {
				$(this).remove();
			});
			$('#' + uploaderId + '-container .drag-drop-inside').show();
		});

		// set post ID into gallery popup button
		galleryLink.attr('href', galleryLink.attr('href') + '&post_id=' + postId);
		// flag the active input for ThickBox so it can populate the appropriate field upon returning
		galleryLink.on('click', function() {
			$(this).addClass('active-gallery-uploader');
		});
	}

	// remainder logic heavily inspired by metabox plugin: http://wordpress.org/extend/plugins/meta-box/
	// and this tutorial: http://www.krishnakantsharma.com/2012/01/image-uploads-on-wordpress-admin-screens-using-jquery-and-new-plupload/
	function handleUpload(up, files)
	{
		var uploadContainer = $('#' + up.settings.container),
			maxUploads = uploadContainer.data('max-uploads'),
			uploaded = $('ul.uploaded-images', uploadContainer).children().length,
			msg = 'You may only upload ' + maxUploads + ' file';

		if (maxUploads > 1) {
			msg += 's';
		}

		// Remove files from queue if exceed max file uploads
		if ( ( uploaded + files.length ) > maxUploads )
		{
			for (var i = files.length; i--;)
			{
				up.removeFile(files[i]);
			}
			alert(msg);     // :TODO: nicer error message
			return false;
		}

		// Hide drag & drop section if reach max file uploads, show it otherwise
		if (( uploaded + files.length ) == maxUploads) {
			uploadContainer.find('.drag-drop-inside').hide();
		} else {
			uploadContainer.find('.drag-drop-inside').show();
		}

		var max = parseInt(up.settings.max_file_size, 10);

		// handle the actual uploading
		plupload.each(files, function(file) {
			createUploadLoader(up, file);
			if (file.size >= max) {
				handleUploadError(up, file, 'File too large');
			}
		});

		up.refresh();
		up.start();
	}

	function handleUploadProgress(up, file)
	{
		$('li#' + file.id + " .progress-bar")
			.width(file.percent + "%")
			.find('span').html(plupload.formatSize(parseInt(file.size * file.percent / 100)));
	}

	function handleUploadComplete(up, file, response)
	{
		if (!response.response || response.status != 200) {
			handleUploadError(up, file, 'Error processing upload');
			return;
		}

		response = JSON.parse(response.response);

		if (response.error) {
			handleUploadError(up, file, response.error);
			return;
		}

		$('li#' + file.id).replaceWith(response.html);
	}

	function createUploadLoader(up, file)
	{
		// :TODO: could use local file API to read in pre-upload here...
		var uploadList = $('#' + up.settings.container + ' ul.uploaded-images');
		uploadList.append( "<li id=\"" + file.id + "\" class=\"loading\"><div class='progress-bar'>Uploading (<span>0%</span>)</div></li>" );
	}

	function handleUploadError(up, file, msg)
	{
		var uploadContainer = $('#' + up.settings.container);
		uploadContainer.find('.drag-drop-inside').show();   // we can assume an error means the ability to upload has been restored

		$('li#' + file.id)
			.removeClass('loading').addClass('error').html('<div class="details">'+msg+'</div>')
			.delay(1600)
			.fadeOut('slow', function() {
				$(this).remove();
			});
	}

	// handler for use by media library when selecting from there
	window.PB_handle_media_selection = function(attachmentID, attachData)
	{
		// close the uploader box first, we're done
		tb_remove();

		// render a new item to the active list
		var uploaderEl = $('.pb-media-gallery.active-gallery-uploader').closest('.row.richupload'),
			inputName = uploaderEl.data('field'),
			storedItemList = uploaderEl.find('ul.uploaded-images'),
			newItemHTML;

		// @see FormIOField_Richupload::$imageBuildString and FormIOField_Richupload::$fileBuildString
		if (attachData.is_image) {
			newItemHTML = '<li>' +
				'<input type="hidden" name="' + inputName + '[]" value="' + attachmentID + '" />' +
				'<img src="' + attachData.url + '" />' +
				'<div class="img-controls"><a href="' + attachData.edit_url + '" target="_blank">Edit</a><a href="#" class="img-del">&times;</a></div>' +
			'</li>';
		} else {
			newItemHTML = '<li>' +
				'<input type="hidden" name="' + inputName + '[]" value="' + attachmentID + '" />' +
				'<div class="details"><a href="' + attachData.url + '">' + attachData.filename + '</a><br />[' + attachData.mimetype + ']</div>' +
				'<div class="img-controls"><a href="' + attachData.edit_url + '" target="_blank">Edit</a><a href="#" class="img-del">&times;</a></div>' +
			'</li>';
		}

		storedItemList.append(newItemHTML);
	};

	//--------------------------------------------------------------------------
	// totally shonky binding for Wordpress link manager
	//--------------------------------------------------------------------------

	var linkManagerRebound = false,
		linkDlgTarget = null,
		oldTinyMCEpopup, oldActiveEditor;

	function bindLinkDialog(el)
	{
		el.on('click', '.wp-link-dlg-open input', function(e) {
			linkDlgTarget = $(this).closest('div.external-link');

			oldActiveEditor = wpActiveEditor;
			oldTinyMCEpopup = tinyMCEPopup;
			wpActiveEditor = true;
			tinyMCEPopup = false;

			wpLink.open();
			wpLink.textarea = $();  // a focus will scroll us confusingly...

			$('#link-title-field').val(linkDlgTarget.find('.row.name :input').val());
			$('#url-field').val(linkDlgTarget.find('.row.href :input').val());
			if (linkDlgTarget.find('.row.target :checked').length) {
				$('#link-target-checkbox').attr('checked', 'checked');
			} else {
				$('#link-target-checkbox').removeAttr('checked');
			}
		});

		$('body').on('click', '#wp-link-submit', function(event) {
			if (!linkDlgTarget) {
				return;
			}

			var linkAtts = wpLink.getAttrs();

			linkDlgTarget.find('.row.href :input').val(linkAtts.href);
			linkDlgTarget.find('.row.name :input').val(linkAtts.title);
			if (linkAtts.target == '_blank') {
				linkDlgTarget.find('.row.target :checkbox').attr('checked', 'checked');
			} else {
				linkDlgTarget.find('.row.target :checkbox').removeAttr('checked');
			}

			linkDlgTarget = null;
			tinyMCEPopup = oldTinyMCEpopup;
			wpActiveEditor = oldActiveEditor;

			wpLink.textarea = $();  // a focus will scroll us confusingly...
			wpLink.close();
		}).on('click', '#wp-link-cancel', function(event) {
			linkDlgTarget = null;
			tinyMCEPopup = oldTinyMCEpopup;
			wpActiveEditor = oldActiveEditor;
		});
	}

	//--------------------------------------------------------------------------
	// DOM load
	//--------------------------------------------------------------------------

	var SUBMIT_BTN = null;	// button used to submit post page forms

	$(function() {
		// find our metaboxs
		var metaboxes = $('input[name=custom_post_type]').parent();

		// add img class to formIO image input parents to allow separate styling of actual tokeninput input
		$('li.img.token-input-token').closest('token-input-list').addClass('img');

		if (metaboxes.get(0)) {
			// bind formIO behaviours to post box elements
			initPostBoxes(metaboxes);

			// load the clicked button when saving a post
			function setSubmitter(e) {
				SUBMIT_BTN = $(this).attr('id');
			}
			$('#save-post').on('mousedown', setSubmitter);
			$('#publish').on('mousedown', setSubmitter);
		}

		// load parallax preview of image attachments
		initThumbParallax($('ul.token-input-list li.img div'));

		//--------------------------------------------------------------------------
		// custom taxonomy inputs for non-post pages

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

})(jQuery);
