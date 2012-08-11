<?php
/**
 * Input field which behaves like a posts input except for the assignment of users.
 *
 * Arguments to setQueryArgs are the same as to the WP_User_Query class
 *
 * @package wpBackendBase
 * @author Sam Pospischil <pospi@spadgos.com>
 */

class FormIOField_Users extends FormIOField_Posttypes
{
	protected static $DEFAULT_POST_TYPE = 'user';
	protected static $DEFAULT_QUERY_ARGS = array(
		'limit' => -1,
		'fields' => 'all_with_meta',
		'order_by' => 'login',
	);

	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		$vars['behaviour'] = 'posttype_user';
		return $vars;
	}

	public function runRequest($searchVal)
	{
		global $wpdb;

		$qargs = array_merge($this->queryArgs, $this->handleSearchInput($searchVal));

		$this->prehandleQueryArgs($qargs);

		$users = array();
		if (isset($qargs['role']) && is_array($qargs['role'])) {
			// query for multiple roles
			foreach ($qargs['role'] as $role) {
				$q = new WP_User_Query(array('role' => $role) + $qargs);
				$results = $q->get_results();

				if ($results) {
					$users = array_merge($users, $results);
				}
			}
		} else {
			// normal query
			$q = new WP_User_Query($qargs);
			$users = $q->get_results();
		}

		$this->results = $users;

		return $this->getQueryResults();
	}

	protected function getQueryResults()
	{
		$postIds = array();
		foreach ($this->results as $post) {
			$postResult = array(
				'name' => isset($post->data->user_firstname) ? ($post->data->user_firstname . ' ' . $post->data->user_lastname) : $post->data->display_name,
				'id' => $post->ID,
			);
			$this->addPostTypeVars($postResult, $post);

			$postIds[$post->ID] = $postResult;
		}

		return $postIds;
	}

	protected function updateNewUrl($postType)
	{
		$this->setAttribute('newItemUrl', AdminMenu::getNewUrl('user'));
	}

	//--------------------------------------------------------------------------

	protected function addPostTypeVars(&$vars, $user)
	{
		$vars['editUrl'] = AdminMenu::getEditUrl($user);
		$vars['emailAddr'] = $user->data->user_email;
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
			'search' => $str . '*',
		);
	}
}
