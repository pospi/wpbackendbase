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
	protected static $DEFAULT_POST_TYPE = 'link';
	protected static $DEFAULT_QUERY_ARGS = array(
		'limit' => -1,
	);

	public function runRequest($searchVal)
	{
		$this->results = get_bookmarks($this->queryArgs);

		$postIds = array();
		foreach ($this->results as $post) {
			$postResult = array(
				'label' => $post->link_name,
				'value' => $post->link_id,
			);
			$this->addPostTypeVars($postResult, $post);

			$postIds[] = $postResult;
		}

		return $postIds;
	}

	//--------------------------------------------------------------------------

	protected function addPostTypeVars(&$vars, $link)
	{
		$vars['editUrl'] = 'wp-admin/link.php?action=edit&link_id=' . $link->link_id;
	}
}
?>
