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
	);

	protected $optionNum = 0;		// internal counter for associating options with post results by index

	public function __construct($form, $name, $displayText = null, $defaultValue = null)
	{
		parent::__construct($form, $name, $displayText, $defaultValue);

		$this->setMultiple();
	}

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
		$qargs = $this->queryArgs;

		$this->results = new WP_Query($qargs);
		$this->results = $this->results->posts;

		$postIds = array();
		foreach ($this->results as $post) {
			$postResult = array(
				'label' => $post->post_title,
				'value' => $post->ID,
			);
			$this->addPostTypeVars($postResult, $post);

			$postIds[] = $postResult;
		}

		return $postIds;
	}

	protected function addPostTypeVars(&$vars, $post)
	{
		$vars['editUrl'] = "wp-admin/post.php?action=edit&post=" . $post->ID;
	}
}
?>
