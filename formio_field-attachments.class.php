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
	public $standardBuildString;
	public $imageFieldBuildString = '<div><a href="{$viewPostUrl}" target="_blank"><img src="{$postThumbUrl}" /></a><label><input type="checkbox" name="{$name}[{$value}]"{$disabled? disabled="disabled"}{$checked? checked="checked"} /></a> <a href="{$editPostUrl}">{$postTitle}</label></div>';

	protected static $DEFAULT_QUERY_ARGS = array(
		'post_type' => 'attachment',
		'post_status' => 'inherit',		// needed to query for attachment post types
		'posts_per_page' => -1,
	);

	public function __construct($form, $name, $displayText = null, $defaultValue = null)
	{
		$this->standardBuildString = $this->subfieldBuildString;
		parent::__construct($form, $name, $displayText, $defaultValue);
	}

	public function setQueryArgs($attachmentType = 'image', Array $args)
	{
		if (!isset($attachmentType)) $attachmentType = 'image';
		$args['post_mime_type'] = $attachmentType;
		$args = array_merge($args, self::$DEFAULT_QUERY_ARGS);

		$this->queryArgs = $args;

		if ($attachmentType == 'image') {
			$this->subfieldBuildString = $this->imageFieldBuildString;
		} else {
			$this->subfieldBuildString = $this->standardBuildString;
		}

		$this->rebuildResults();
	}

	//--------------------------------------------------------------------------

	protected function addPostTypeVars(&$vars)
	{
		$vars['postTitle'] = $this->results[$this->optionNum]->post_title;
		$vars['editPostUrl'] = 'wp-admin/media.php?action=edit&attachment_id=' . $this->results[$this->optionNum]->ID;
		$vars['viewPostUrl'] = wp_get_attachment_url($this->results[$this->optionNum]->ID);
		$vars['postThumbUrl'] = wp_get_attachment_thumb_url($this->results[$this->optionNum]->ID);
	}
}
?>
