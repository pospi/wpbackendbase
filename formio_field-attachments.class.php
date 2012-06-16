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
	public $imageFieldBuildString = '<label><a href="{$viewPostUrl}"><img src="{$postThumbUrl}" /><input type="checkbox" name="{$name}[{$value}]"{$disabled? disabled="disabled"}{$checked? checked="checked"} /></a> <a href="{$editPostUrl}">{$postTitle}</a></label>';

	protected static $DEFAULT_QUERY_ARGS = array(
		'post_type' => 'attachment',
		'post_status' => 'inherit',		// needed to query for attachment post types
		'posts_per_page' => -1,
	);

	public function setQueryArgs($attachmentType = 'image', Array $args)
	{
		$args['post_mime_type'] = $attachmentType;
		$args = array_merge($args, self::$DEFAULT_QUERY_ARGS);

		$this->queryArgs = $args;

		$this->rebuildResults();
	}

	//--------------------------------------------------------------------------

	protected function addPostTypeVars(&$vars)
	{
		$vars['postTitle'] = $this->results[$this->optionNum]->post_title;
		$vars['editPostUrl'] = $this->results[$this->optionNum]->ID;
		$vars['viewPostUrl'] = $this->results[$this->optionNum]->ID;
		$vars['postThumbUrl'] = $this->results[$this->optionNum]->ID;
	}
}
?>
