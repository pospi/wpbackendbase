<?php
/**
 * Posts input field.
 *
 * Allows loading a list of post objects from wordpress based on any criteria
 * applicable to WP_Query / query_posts(). Objects can then be chosen from the list
 * for association with other posts via metadata, etc.
 *
 * An array of post IDs is returned as the value of this field.
 *
 * @author Sam Pospischil <pospi@spadgos.com>
 */

require_once(FORMIO_FIELDS . 'formio_field-multiple.class.php');

class FormIOField_Posttypes extends FormIOField_Autocomplete
{
	const DEFAULT_POST_LIMIT = 30;
	protected static $DEFAULT_POST_TYPE = 'post';

	protected $results;
	protected $queryArgs;

	protected static $DEFAULT_QUERY_ARGS = array(
		'posts_per_page' => -1,
		'orderby' => 'title',
	);

	protected $optionNum = 0;		// internal counter for associating options with post results by index

	public function __construct($form, $name, $displayText = null, $defaultValue = null)
	{
		parent::__construct($form, $name, $displayText, $defaultValue);

		$this->setMultiple();
	}

	/**
	 * load post titles in place of IDs for human readable display
	 */
	public function getHumanReadableValue()
	{
		$value = $this->getArrayValue();

		if (!$this->results) {
			$ids = $this->runRequest(null);
		} else {
			$ids = $this->handleQueryResults();
		}
		$friendlyValsArr = array();

		foreach ($value as $id) {
			$friendlyValsArr[] = $ids[$id]['name'];
		}

		return implode($this->getAttribute('delimiter', self::DEFAULT_DELIM), $friendlyValsArr);
	}

	//--------------------------------------------------------------------------

	/**
	 * Sets the post type & arguments to WP_Query for this input's list of options.
	 * By default, 30 posts are read per request.
	 * @param string $type wordpress post type to read
	 */
	public function setQueryArgs($postType, Array $args)
	{
		$self = get_class($this);

		if (!isset($postType)) $postType = $self::$DEFAULT_POST_TYPE;
		$args['post_type'] = $postType;
		$args = array_merge($args, $self::$DEFAULT_QUERY_ARGS);

		// update autocomplete url to load correct post type
		$this->updateAutocompleteUrl($args['hostposttype'], $args['metabox'], $args['metakey']);
		unset($args['hostposttype']);
		unset($args['metabox']);
		unset($args['metakey']);

		$this->queryArgs = $args;
	}

	protected function updateAutocompleteUrl($postType, $metabox, $metakey)
	{
		$this->setAutocompleteUrl(plugins_url("pospi_base/posttype-autocomplete.php?pt={$postType}&form={$metabox}&field={$metakey}"));
	}

	//--------------------------------------------------------------------------

	/**
	 * Handles a POST request for autocomplete data
	 */
	public function runRequest($searchVal)
	{
		$qargs = array_merge($this->queryArgs, $this->handleSearchInput($searchVal));

		$this->prehandleQueryArgs($qargs);

		$q = new WP_Query($qargs);
		$this->results = $q->posts;

		return $this->handleQueryResults();
	}

	protected function prehandleQueryArgs(&$qargs)
	{
		global $wpdb;

		// handle searches of post title by using an IN query on matching IDs
		if (isset($qargs['post_title_in'])) {
			$words = $qargs['post_title_in'];
			$mypostids = $wpdb->get_col("select ID from $wpdb->posts where post_title LIKE '%". implode("%' OR post_title LIKE '%", $words) ."%'");
			$qargs['post__in'] = $mypostids;

			unset($qargs['post_title_in']);
		}
	}

	protected function handleQueryResults()
	{
		$postIds = array();
		foreach ($this->results as $post) {
			$postResult = array(
				'name' => $post->post_title,
				'id' => $post->ID,
			);
			$this->addPostTypeVars($postResult, $post);

			$postIds[$post->ID] = $postResult;
		}

		return $postIds;
	}

	/**
	 * Add other variables to output with each autocomplete list item
	 * @param array  &$vars array of item variables to append to
	 * @param object $post  wordpress post object (or other DB record) being output
	 */
	protected function addPostTypeVars(&$vars, $post)
	{
		$vars['editUrl'] = "wp-admin/post.php?action=edit&post=" . $post->ID;
	}

	/**
	 * Turns the search input string into query arguments for WP_Query
	 * @param  string $str query string passed
	 * @return array for merging into WP_Query options
	 */
	protected function handleSearchInput($str)
	{
		$words = preg_split('/\s+/', trim($str));
		if (!$str || !$words) {
			return array();
		}

		return array(
			'post_title_in' => $words,
		);
	}
}
