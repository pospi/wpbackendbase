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
		'orderby' => 'name',
	);

	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		$vars['behaviour'] = 'posttype_link';
		return $vars;
	}

	public function runRequest($searchVal)
	{
		global $wpdb;

		$qargs = array_merge($this->queryArgs, $this->handleSearchInput($searchVal));

		$this->prehandleQueryArgs($qargs);

		$this->results = get_bookmarks($qargs);

		return $this->getQueryResults();
	}

	protected function getQueryResults()
	{
		$postIds = array();
		foreach ($this->results as $post) {
			$postResult = array(
				'name' => $post->link_name,
				'id' => $post->link_id,
			);
			$this->addPostTypeVars($postResult, $post);

			$postIds[$post->link_id] = $postResult;
		}

		return $postIds;
	}

	protected function updateNewUrl($postType)
	{
		$this->setAttribute('newItemUrl', AdminMenu::getNewUrl('link'));
	}

	//--------------------------------------------------------------------------

	protected function addPostTypeVars(&$vars, $link)
	{
		$vars['editUrl'] = AdminMenu::getEditUrl($link);
		$vars['linkUrl'] = $link->link_url;
	}

	/**
	 * Turns the search input string into query arguments for WP_Query
	 * @param  string $str query string passed
	 * @return array for merging into WP_Query options
	 */
	protected function handleSearchInput($str)
	{
		if (!$str) {
			return array();
		}
		return array(
			'search' => $str,
		);
	}
}
