<?php
/**
 * Input field which behaves like a posts input except for the assignment of links.
 *
 * Arguments to setQueryArgs are the same as to get_bookmarks():
 * 	http://codex.wordpress.org/Function_Reference/get_bookmarks
 *
 * @author Sam Pospischil <pospi@spadgos.com>
 */

class FormIOField_Links extends FormIOField_Posttypes
{
	protected static $DEFAULT_QUERY_ARGS = array(
		'limit' => -1,
	);

	protected function rebuildResults()
	{
		$this->results = get_bookmarks($this->queryArgs);

		$postIds = array();
		foreach ($this->results as $post) {
			$postIds[$post->link_id] = $post->link_name;
		}

		$this->setOptions($postIds);
	}

	//--------------------------------------------------------------------------

	protected function addPostTypeVars(&$vars)
	{
		$vars['postTitle'] = $this->results[$this->optionNum]->link_name;
		$vars['editPostUrl'] = $this->results[$this->optionNum]->link_id;
	}
}
?>
