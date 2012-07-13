<?php
/**
 * Advanced post type input field for the display of attachment post types.
 *
 * Adds thumbnail drawing for image attachments. post_type for this class
 * configures the post_mime_type internally - the post type is always 'attachment'.
 *
 * @author Sam Pospischil <pospi@spadgos.com>
 */

class FormIOField_Attachments extends FormIOField_Posttypes
{
	protected static $DEFAULT_QUERY_ARGS = array(
		'post_type' => 'attachment',
		'post_status' => 'inherit',		// needed to query for attachment post types
		'posts_per_page' => -1,
		'orderby' => 'title',
	);

	private $isImage = false;

	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		$vars['behaviour'] = 'posttype_attachment' . ($this->isImage ? '_image' : '');
		return $vars;
	}

	public function setQueryArgs($attachmentType = null, Array $args)
	{
		$self = get_class($this);

		if (!isset($attachmentType)) $attachmentType = 'image';
		$args['post_mime_type'] = $attachmentType;
		$args = array_merge($args, $self::$DEFAULT_QUERY_ARGS);

		if ($attachmentType == 'image') {
			$this->isImage = true;
		} else {
			$this->isImage = false;
		}

		// update autocomplete url to load correct post type
		$this->updateAutocompleteUrl($args['hostposttype'], $args['metabox'], $args['metakey']);
		unset($args['hostposttype']);
		unset($args['metabox']);
		unset($args['metakey']);

		$this->queryArgs = $args;
	}

	//--------------------------------------------------------------------------

	protected function addPostTypeVars(&$vars, $post)
	{
		$vars['editUrl'] = 'media.php?action=edit&attachment_id=' . $post->ID;
		$vars['viewUrl'] = wp_get_attachment_url($post->ID);
		if ($this->isImage) {
			$vars['thumbUrl'] = wp_get_attachment_thumb_url($post->ID);
		}
	}
}
