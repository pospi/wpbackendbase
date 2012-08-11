<?php
/**
 * Simple field class for showing links in admin forms
 *
 * @package wpBackendBase
 * @author Sam Pospischil <pospi@spadgos.com>
 */

class FormIOField_Displaylink extends FormIOField_Raw
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}">
		<label for="{$id}">{$desc}</label>
		<a id="{$id}" href="{$url}" target="_blank"{$alt? alt="$alt"}>{$url}</a>
		{$hint? <p class="hint">$hint</p>}
	</div>';

	public function __construct($form, $name, $displayText = null, $defaultValue = null)
	{
		parent::__construct($form, $name, $displayText, $defaultValue);

		// link 'value' is actually the URL attribute since this field is presentational and has no value
		$this->setValue($defaultValue);
	}

	public function setValue($value)
	{
		$this->setAttribute('url', $value);
	}
}
?>
