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
 * @package wpBackendBase
 * @author Sam Pospischil <pospi@spadgos.com>
 */

require_once(FORMIO_FIELDS . 'formio_field-multiple.class.php');

class FormIOField_Posttypes extends FormIOField_Autocomplete
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}">
		<label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label>
		<input type="hidden" name="{$name}"{$value? value="$value"}{$extradata? data-fio-value-metadata="$extradata"} />
		<input type="text" name="{$friendlyName}" id="{$id}"{$friendlyValue? value="$friendlyValue"}{$readonly? readonly="readonly"}{$maxlen? maxlength="$maxlen"}{$behaviour? data-fio-type="$behaviour"}{$validation? data-fio-validation="$validation"} data-fio-searchurl="{$searchurl}"{$multiple? data-fio-multiple="$multiple"}{$delimiter? data-fio-delimiter="$delimiter"}{$dependencies? data-fio-depends="$dependencies"} />
		{$newItemUrl?$newItemUrl}
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';

	const AJAX_HOOK_NAME = 'wp_ajax_posttype_input_autocomplete';		// these should be bound to FormIOField_Posttypes::__responseHandler
	const AJAX_HOOK_NAME_FRONTEND = 'wp_ajax_nopriv_posttype_input_autocomplete';	// only use if you want available from the frontend!
	const DEFAULT_POST_LIMIT = 30;

	protected static $DEFAULT_POST_TYPE = 'post';

	protected $results;
	protected $queryArgs = array();

	protected static $DEFAULT_QUERY_ARGS = array(
		'posts_per_page' => -1,
		'orderby' => 'title',
		'order' => 'ASC',
	);

	protected $optionNum = 0;		// internal counter for associating options with post results by index

	public function __construct($form, $name, $displayText = null, $defaultValue = null)
	{
		parent::__construct($form, $name, $displayText, $defaultValue);

		$this->setMultiple();

		$self = get_class($this);
		$this->updateNewUrl($self::$DEFAULT_POST_TYPE);
	}

	/**
	 * load post titles in place of IDs for human readable display
	 */
	public function getHumanReadableValue()
	{
		$value = $this->getValue();
		$ids = $this->forceGetQueryResults();

		$friendlyValsArr = array();

		if ($value) {
			if (!is_array($value)) {
				$value = array($value);
			}
			foreach ($value as $id) {
				$friendlyValsArr[] = $ids[$id]['name'];
			}
		}

		return implode($this->getAttribute('delimiter', self::DEFAULT_DELIM), $friendlyValsArr);
	}

	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();

		// add value metadata for js UIs to read from
		$internalValue = $this->getValue();
		if ($internalValue) {
			if (!is_array($internalValue)) {
				$internalValue = array($internalValue);
			}

			$visiblePosts = $this->forceGetQueryResults();

			$extradata = array();
			foreach ($internalValue as $k => $postId) {
				$extradata[$k] = $visiblePosts[$postId];
			}

			$vars['extradata'] = htmlspecialchars(json_encode($extradata));	// allows passing other label data to jquery.tokeninput & other plugins wishing to read it
		}

		// new item URL should only be show on admin pages
		if (is_admin()) {
			$vars['newItemUrl'] = "<a class=\"new\" href=\"{$vars['newItemUrl']}\" target=\"_blank\">New</a>";
		} else {
			unset($vars['newItemUrl']);
		}

		if (is_array($internalValue)) {
			$vars['value'] = implode($this->getAttribute('delimiter', self::DEFAULT_DELIM), $internalValue);
		}

		return $vars;
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
		if (isset($args['metabox'])) {
			$this->updateAutocompleteUrl($args['hostposttype'], $args['metabox'], $args['metakey']);
			unset($args['hostposttype']);
			unset($args['metabox']);
			unset($args['metakey']);
		} else if (isset($args['fio_form'])) {
			$this->updateFrontendAutocompleteUrl($args['fio_form'], $args['fio_field']);
			unset($args['fio_form']);
			unset($args['fio_field']);
		}

		$this->queryArgs = $args;

		$this->updateNewUrl($postType);
	}

	protected function updateNewUrl($postType)
	{
		$this->setAttribute('newItemUrl', AdminMenu::getNewUrl($postType));
	}

	protected function updateAutocompleteUrl($postType, $metabox, $metakey)
	{
		$this->setAutocompleteUrl(admin_url("admin-ajax.php?action=" . preg_replace('/^wp_ajax_/', '', self::AJAX_HOOK_NAME) . "&pt={$postType}&form={$metabox}&field={$metakey}"));
	}

	protected function updateFrontendAutocompleteUrl($formSlug, $fieldID)
	{
		$this->setAutocompleteUrl(admin_url("admin-ajax.php?action=" . preg_replace('/^wp_ajax_nopriv_/', '', self::AJAX_HOOK_NAME_FRONTEND) . "&fio_form={$formSlug}&fio_field={$fieldID}"));
	}

	//--------------------------------------------------------------------------

	// This should be bound to an appropriate admin ajax action
	public static function __responseHandler()
	{
		// load args
		$formId = isset($_GET['form']) ? $_GET['form'] : null;
		$fieldKey = isset($_GET['field']) ? $_GET['field'] : null;

		if ($formId === null && $fieldKey === null && class_exists('FormIOBuilder')) {
			// not from backend. attempt loading from FormIO plugin frontend if the plugin is installed.
			$formId = isset($_GET['fio_form']) ? $_GET['fio_form'] : null;
			$fieldKey = isset($_GET['fio_field']) ? $_GET['fio_field'] : null;

			$form = FormIOBuilder::loadForm($formId);
		} else {
			// load post type class & ensure form inputs have been setup
			$postType = isset($_GET['pt']) ? $_GET['pt'] : 'post';
			$postType = Custom_Post_Type::get_post_type($postType);
			$postType->init_form_handlers();

			// load field by name
			$form = $postType->formHandlers[$formId];
		}

		if (!$form) {
			return;
		}

		$field = $form->getField($fieldKey);

		if (!$field) {
			return;
		}

		// run field query & output it
		header('Content-type: application/json');
		echo json_encode(array_values($field->runRequest($_GET['term'])));

		die;
	}

	/**
	 * Handles a POST request for autocomplete data
	 */
	public function runRequest($searchVal)
	{
		$qargs = array_merge($this->queryArgs, $this->handleSearchInput($searchVal));

		$this->prehandleQueryArgs($qargs);

		$q = new WP_Query($qargs);
		$this->results = $q->posts;

		return $this->getQueryResults();
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

	protected function getQueryResults()
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
		$vars['editUrl'] = AdminMenu::getEditUrl($post);
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

	private function forceGetQueryResults()
	{
		if (!$this->results) {
			return $this->runRequest(null);
		} else {
			return $this->getQueryResults();
		}
	}
}
