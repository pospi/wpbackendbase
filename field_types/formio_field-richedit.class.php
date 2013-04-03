<?php
/**
 * Input class for displaying a wordpress TinyMCE editor
 *
 * @package wpBackendBase
 * @author Sam Pospischil <pospi@spadgos.com>
 */

class FormIOField_Richedit extends FormIOField_Text
{
	public function __construct($form, $name, $displayText = null, $defaultValue = null)
	{
		$this->buildString = '<div class="row richedit{$alt? alt}{$classes? $classes}">
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

		// defaults for the editor inputs. Any parameters to wp_editor() can be passed in as field attributes.
		$editorDefaults = array(
			'textarea_name' => $this->getName(),
			'media_buttons' => false,
			'teeny' => true,
		);

		// use output buffering to capture the editor content. sorry compatibilty :/
		ob_start();
		wp_editor($this->value, $this->getFieldId(), array_merge($editorDefaults, $this->getAttributes()));
		$vars['editor'] = ob_get_clean();

		return $vars;
	}
}
