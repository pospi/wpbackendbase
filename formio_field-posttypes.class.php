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

class FormIOField_Posttypes extends FormIOField_Multiple
{
	public $buildString = '<fieldset id="{$id}" class="row multiple posttype col{$columns}{$alt? alt}"{$dependencies? data-fio-depends="$dependencies"}{$validation? data-fio-validation="$validation"}><legend>{$desc}{$required? <span class="required">*</span>}</legend>{$options}{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></fieldset>';
	public $subfieldBuildString = '<label><input type="checkbox" name="{$name}[{$value}]"{$disabled? disabled="disabled"}{$checked? checked="checked"} /> <a href="{$editPostUrl}">{$postTitle}</a></label>';

	protected $results;
	protected $queryArgs;

	protected static $DEFAULT_QUERY_ARGS = array(
		'posts_per_page' => -1,
	);

	protected $optionNum = 0;		// internal counter for associating options with post results by index

	/**
	 * Sets the post type & arguments to WP_Query for this input's list of options.
	 * By default, all posts are read.
	 * @param string $type wordpress post type to read
	 */
	public function setQueryArgs($postType = 'post', Array $args)
	{
		$args['post_type'] = $postType;
		$args = array_merge($args, self::$DEFAULT_QUERY_ARGS);

		$this->queryArgs = $args;

		$this->rebuildResults();
	}

	protected function rebuildResults()
	{
		$qargs = $this->queryArgs;

		$this->results = new WP_Query($qargs);
		$this->results = $this->results->posts;

		$postIds = array();
		foreach ($this->results as $post) {
			$postIds[$post->ID] = $post->post_title;
		}

		$this->setOptions($postIds);
	}

	//--------------------------------------------------------------------------

	public function getHumanReadableValue()
	{
		$output = array();
		$val = $this->getValue();
		if (is_array($val)) {
			foreach ($val as $idx => $choice) {
				if ($choice) {		// this is the only check we need here since unsent checkboxes will not even be set
					$output[] = $this->options[$idx];
				}
			}
		}

		return implode("\n", $output);
	}

	protected function getNextOptionVars()
	{
		if (!$vars = parent::getNextOptionVars()) {
			$this->optionNum = 0;
			return false;
		}

		$vars['name'] = $this->getName();

		if (isset($this->value[$vars['value']])) {
			$val = $this->value[$vars['value']];
			$vars['checked'] = ($val === true || $val === 'on' || $val === 'true' || (is_numeric($val) && $val > 0));
		}

		$this->addPostTypeVars($vars);

		++$this->optionNum;

		return $vars;
	}

	protected function addPostTypeVars(&$vars)
	{
		$vars['postTitle'] = $this->results[$this->optionNum]->post_title;
		$vars['editPostUrl'] = $this->results[$this->optionNum]->ID;
	}
}
?>
