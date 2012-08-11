<?php
/**
 * Field class for displaying one of the Wordpress taxonomy inputs
 *
 * @package wpBackendBase
 * @author Sam Pospischil <pospi@spadgos.com>
 */

class FormIOField_Taxonomy extends FormIOField_Text
{
	private $taxonomyName = 'category';
	private $activePost = null;		// this determines the value filled out in the input

	public $buildString = '<div class="row blck tax_input{$alt? alt}{$classes? $classes}">
		<label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label>
		<div class="rows">
			{$input}
		</div>
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';

	// :WARNING: must be called before drawing
	public function setTaxonomy($taxName)
	{
		$this->taxonomyName = $taxName;
	}

	// :WARNING: must be called before drawing
	public function setActivePost($post)
	{
		$this->activePost = $post;
	}

	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();

		$vars['input'] = AdminUI::getTaxonomyInput($this->taxonomyName, $this->activePost, $this->getAttribute('hierarchical', false));

		return $vars;
	}
}
