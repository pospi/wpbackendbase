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
		global $wpdb;

		$qargs = array_merge($this->queryArgs, $this->handleSearchInput($searchVal));

		$this->prehandleQueryArgs($qargs);

		$this->results = get_bookmarks($qargs);

		return $this->handleQueryResults();
	}

	protected function handleQueryResults()
	{
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

	/**
	 * Turns the search input string into query arguments for WP_Query
	 * @param  string $str query string passed
	 * @return array for merging into WP_Query options
	 */
	protected function handleSearchInput($str)
	{
		return array(
			'search' => $str,
		);
	}
}
