<?php
/**
 * Input class for displaying a wordpress TinyMCE editor
 */

class FormIOField_Richedit extends FormIOField_Text
{
	public function __construct($form, $name, $displayText = null, $defaultValue = null)
	{
		$this->buildString = '<div class="row{$alt? alt}{$classes? $classes}">
			<label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label>
			{$editor}
			{$error?<p class="err">$error</p>}
			{$hint? <p class="hint">$hint</p>}
		</div>';

		parent::__construct($form, $name, $displayText, $defaultValue);
	}

	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();

		// user output buffering to capture the editor content. sorry compatibilty :/
		ob_start();
		wp_editor($this->value, $this->getName());
		$vars['editor'] = ob_get_clean();

		return $vars;
	}
}