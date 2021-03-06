<?php
/**
 * Simple textual input with some JS wrapped around it to make its intention clearer
 *
 * @package wpBackendBase
 * @author Sam Pospischil <pospi@spadgos.com>
 */

class FormIOField_Twitter_user extends FormIOField_Text
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}">
		<label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label>
		<span class="twitter">
			<a target="_blank" href="https://twitter.com/%s">https://twitter.com/</a>
			<input type="text" name="{$name}" id="{$id}"{$value? value="$value"}{$readonly? readonly="readonly"} data-fio-type="twitter_user"{$validation? data-fio-validation="$validation"}{$dependencies? data-fio-depends="$dependencies"} />
		</span>
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';
}
