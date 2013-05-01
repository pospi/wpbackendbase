<?php
/**
 * Input class for displaying a wordpress plUpload media handler
 *
 * :TODO: clean up attachments which were inserted and removed without being retained
 * :TODO: use WP 3.5+ media library handler where available for selecting from gallery
 * :TODO: provide a parameter to choose whether removing images means deleting them or just unassigning them
 * :TODO: resolve discrepancy where uploading assigns images instantly and choosing from library requires a save action on the parent post
 *
 * custom attributes:
 * 	- max_attachments	Sets max number of files accepted by this input
 * 	- allowed_type		Sets allowed filetypes for upload, based on MIME type. This pattern will be matched to the start of the file's mime. Use '*' to allow anything.
 * 	- assign_on_save	If true, prevents the image actually being attached to the post's metadata until the post itself is updated. Otherwise it will be attached on upload.
 *
 * @package wpBackendBase
 * @author Sam Pospischil <pospi@spadgos.com>
 */

class FormIOField_Richupload extends FormIOField_Text
{
	private static $UPLOADER_CONFIGURED = false;

	const AJAX_HOOK_NAME = 'wp_ajax_pbase_upload_input_handle';		// bind to to FormIOField_Richupload::__uploadHandler

	public $buildString = '<div class="row richupload{$alt? alt}{$classes? $classes}" data-fio-type="plupload" id="{$id}" data-metabox="{$metabox}" data-field="{$name}" data-posttype="{$posttype}">
		{$uploader_init?<script type="text/javascript">var pbase_plupload_config = $uploader_init;</script>}
		<label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label>
		<div class="plupload-upload-ui" class="hide-if-no-js" id="{$id}-container"{$max_attachments? data-max-uploads="$max_attachments"}>
			<div class="drag-drop-area" id="{$id}-dragdrop">
				<ul class="uploaded-images ui-sortable clearfix">
					{$uploaded}
				</ul>
				<div class="drag-drop-inside">
					<p class="drag-drop-info">Drop files here{$max_attachments? ($max_attachments files max)}</p>
					<p>- or -</p>
					<p class="drag-drop-buttons">
						<input id="{$id}-browse-button" type="button" value="Upload files" class="button" />
						<a id="{$id}-library-button" class="pb-media-gallery button thickbox" href="{$gallery_url}">Choose from library</a>
					</p>
				</div>
				{$nonce}
			</div>
		</div>
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';

	protected $imageBuildString = '<li>
		<input type="hidden" name="%4$s[]" value="%1$d" />
		<img src="%2$s" />
		<div class="img-controls"><a href="%3$s" target="_blank">Edit</a><a href="#" class="img-del">&times;</a></div>
	</li>';

	protected $fileBuildString = '<li>
		<input type="hidden" name="%4$s[]" value="%1$d" />
		<div class="details"><a href="%6$s">%2$s</a><br />[%5$s]</div>
		<div class="img-controls"><a href="%3$s" target="_blank">Edit</a><a href="#" class="img-del">&times;</a></div>
	</li>';

	public function __construct($form, $name, $displayText = null, $defaultValue = null)
	{
		parent::__construct($form, $name, $displayText, $defaultValue);

		// default to only accepting images for upload
		$this->setAttribute('allowed_type', 'image');
	}

	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();

		// build plUpload init variables for the form. We only output these once as they will all be the same.
		$vars['uploader_init'] = self::getDefaultUploaderParams();
		$vars['uploaded'] = $this->getLoadedImagesHTML();
		$vars['nonce'] = wp_nonce_field("pbase-upload-images_" . $this->getFieldId(), "nonce-upload-images_" . $this->getFieldId(), false, false);

		$vars['gallery_url'] = admin_url('media-upload.php') . '?context=pospi-base-uploader&tab=library&type=' . $this->getAttribute('allowed_type') . '&TB_iframe=1';

		return $vars;
	}

	protected function getLoadedImagesHTML()
	{
		$val = $this->getValue();
		if (!$val) {
			return '';
		}
		$images = array();

		foreach ($val as $img) {
			$images[] = $this->getFileCellHTML($img);
		}

		return implode("\n", $images);
	}

	protected function getFileCellHTML($img)
	{
		if (is_scalar($img)) {
			$img = get_post($img);
		}
		$mime = explode('/', $img->post_mime_type, 2);
		if ($mime[0] == 'image') {
			return sprintf($this->imageBuildString, $img->ID, wp_get_attachment_thumb_url($img->ID), AdminMenu::getEditUrl($img), $this->getName());
		}
		return sprintf($this->fileBuildString, $img->ID, basename(wp_get_attachment_url($img->ID)), AdminMenu::getEditUrl($img), $this->getName(), $img->post_mime_type, wp_get_attachment_url($img->ID));
	}

	protected static function getDefaultUploaderParams()
	{
		if (self::$UPLOADER_CONFIGURED) {
			return false;
		}
		self::$UPLOADER_CONFIGURED = true;

		$plupload_init = array(
			'runtimes' => 'html5,silverlight,flash,html4',

			'multiple_queues' => true,
			'max_file_size' => wp_max_upload_size() . 'b',
			'url' => admin_url('admin-ajax.php'),
			'flash_swf_url' => includes_url('js/plupload/plupload.flash.swf'),
			'silverlight_xap_url' => includes_url('js/plupload/plupload.silverlight.xap'),
			'filters' => array( array('title' => __( 'Allowed Files' ), 'extensions' => '*') ),
			'multipart' => true,
			'urlstream_upload' => true,

			// all are defaults, overridden per field configuration
			'browse_button' => 'plupload-browse-button',
			'container' => 'plupload-upload-ui',
			'drop_element' => 'drag-drop-area',
			'file_data_name' => 'async-upload',
			'multi_selection' => true,
			'multipart_params' => array(
				'_ajax_nonce' => "",
				'action' => str_replace('wp_ajax_', '', self::AJAX_HOOK_NAME),
			)
		);

		// Multi-file uploading doesn't currently work in iOS Safari,
		// single-file allows the built-in camera to be used as source for images
		if ( wp_is_mobile() )
			$plupload_init['multi_selection'] = false;

		$plupload_init = apply_filters( 'plupload_init', $plupload_init );

		return json_encode($plupload_init);
	}

	//------------------------------------------------------------------------------------

	// Our upload handler just sends back the image HTML, which includes a hidden ID.
	public static function __uploadHandler()
	{
		// load args
		$formId = isset($_POST['form']) ? $_POST['form'] : null;
		$fieldKey = isset($_POST['field']) ? $_POST['field'] : null;
		$postId = isset($_POST['post_ID']) ? $_POST['post_ID'] : null;

		// load post type class & ensure form inputs have been setup
		$postType = isset($_POST['pt']) ? $_POST['pt'] : 'post';
		$postType = Custom_Post_Type::get_post_type($postType);
		$postType->init_form_handlers();

		// load field by name
		$form = $postType->formHandlers[$formId];

		if (!$form) {
			return;
		}

		$field = $form->getField($fieldKey);

		if (!$field) {
			return;
		}

		check_admin_referer("pbase-upload-images_" . $field->getFieldId());

		header('Content-Type: application/json');

		// check MIME type of the upload
		$allowed = $field->getAttribute('allowed_type');
		if ($allowed && $allowed != '*' && !preg_match('@^' . $allowed . '@', $_FILES['async-upload']['type'])) {
			echo json_encode(array(
				'error' => 'Filetype not permitted',
			));
			exit;
		}

		// run the upload process to handle the sent file
		$file       = $_FILES['async-upload'];
		$file_attr  = wp_handle_upload( $file, array( 'test_form' => false ) );

		// file was rejected internally by Wordpress, abort
		if (!empty($file_attr['error'])) {
			echo json_encode($file_attr);
			exit;
		}

		// create an attachment post to bind the file to
		$attachment = array(
			'guid'           => $file_attr['url'],
			'post_mime_type' => $file_attr['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file['name'] ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$id = wp_insert_attachment( $attachment, $file_attr['file'], $postId );

		if ( ! is_wp_error( $id ) )
		{
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file_attr['file'] ) );

			// assign it back to the post's meta immediately if we are configured to do so
			if (!$field->getAttribute('assign_on_save')) {
				$fieldKey = preg_replace('/^' . Custom_Post_Type::META_POST_KEY . '\[(.*)\]$/', '$1', $fieldKey);
				$existing = get_post_meta($postId, $fieldKey, true);
				if (!$existing) {
					$existing = array();
				}
				$existing[] = $id;
				update_post_meta($postId, $fieldKey, array_unique($existing));
			}

			echo json_encode(array(
				'html' => $field->getFileCellHTML($id),
			));
		} else {
			echo 0;
		}
		exit;
	}

	//------------------------------------------------------------------------------------
	//	Wordpress hooks for integrating into media library

	public static function bindGalleryUI()
	{
		if (is_admin()) {
			add_action('admin_menu', array(get_class(), 'checkUploadContext'));
		}
	}

	public static function checkUploadContext()
	{
		if (isset($_REQUEST['context']) && $_REQUEST['context'] == 'pospi-base-uploader') {
			$cls = get_class();

			add_filter('media_upload_form_url', array($cls, 'configureUploadFormAction'), 10, 2);
			add_filter('media_upload_tabs', array($cls, 'configureLibraryTabs'), 10, 1);
			add_filter('attachment_fields_to_edit', array($cls, 'configureLibraryButton'), 10, 2);
			add_filter('media_send_to_editor', array($cls, 'configureLibrarySubmissionAction'), 10, 3);
		}
	}

	// pass library context back through submission actions
	public static function configureUploadFormAction($url, $type)
	{
		return add_query_arg('context', $_REQUEST['context'], $url);
	}

	// configure available sections of the library in associated popups
	public static function configureLibraryTabs($tabs)
	{
		unset($tabs['type']);
		unset($tabs['type_url']);
		unset($tabs['gallery']);
		return $tabs;
	}

	// change the library 'send back to parent window' button text, and remove all other editing fields from the library window
	public static function configureLibraryButton($form_fields, $img)
	{
		$send = "<input type=\"submit\" class=\"button button-primary\" name=\"send[{$img->ID}]\" value=\"" . esc_attr__( 'Select File' ) . "\" />";

		$mime = explode('/', $img->post_mime_type, 2);
		$isImage = $mime[0] == 'image';

		if ($isImage) {
			$displayUrl = wp_get_attachment_thumb_url($img->ID);
		} else {
			$displayUrl = wp_get_attachment_url($img->ID);
		}
		$editUrl = AdminMenu::getEditUrl($img);
		$filename = basename(wp_get_attachment_url($img->ID));
		$fullMime = $img->post_mime_type;

		return array(
			// override button HTML
			'buttons' => array('tr' => "\t\t<tr class='submit'><td></td><td class='savesend'>$send</td></tr>\n"),

			// send additional data back needed to render the image into the control in the parent window
			'is_image' => array('input' => 'hidden', 'value' => $isImage ? 1 : 0),
			'url' => array('input' => 'hidden', 'value' => $displayUrl),
			'edit_url' => array('input' => 'hidden', 'value' => $editUrl),
			'filename' => array('input' => 'hidden', 'value' => $filename),
			'mimetype' => array('input' => 'hidden', 'value' => $fullMime),
		);
	}

	// change the JS logic run when selecting items from the media library
	public static function configureLibrarySubmissionAction($html, $send_id, $attachment)
	{
		$attachment = json_encode($attachment);	// :NOTE: this is the data from configureLibraryButton() hidden inputs
		?>
			<script type="text/javascript">
				var win = window.dialogArguments || opener || parent || top;

				win.PB_handle_media_selection('<?php echo $send_id;?>', <?php echo $attachment; ?>);
			</script>
		<?php
		exit();
	}
}

FormIOField_Richupload::bindGalleryUI();
