<?php
/**
 * Input class for displaying a wordpress plUpload media handler
 *
 * :TODO: clean up attachments which were inserted and removed without being retained
 * :TODO: allow for inserting existing attachments from the media library
 *
 * custom attributes:
 * 	- max_attachments	Sets max number of files accepted by this input
 * 	- allowed_type		Sets allowed filetypes for upload, based on MIME type. This pattern will be matched to the start of the file's mime. Use '*' to allow anything.
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
					<p class="drag-drop-info">Drop files here</p>
					<p>- or -</p>
					<p class="drag-drop-buttons"><input id="{$id}-browse-button" type="button" value="Select Files" class="button" /></p>
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
	// The image will only be associated and saved when the post is updated / published etc.
	public static function __uploadHandler()
	{
		// load args
		$formId = isset($_POST['form']) ? $_POST['form'] : null;
		$fieldKey = isset($_POST['field']) ? $_POST['field'] : null;

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
		$attachment = array(
			'guid'           => $file_attr['url'],
			'post_mime_type' => $file_attr['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file['name'] ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$id = wp_insert_attachment( $attachment, $file_attr['file'], $post_id );

		if ( ! is_wp_error( $id ) )
		{
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file_attr['file'] ) );

			echo json_encode(array(
				'html' => $field->getFileCellHTML($id),
			));
		} else {
			echo 0;
		}
		exit;
	}
}
