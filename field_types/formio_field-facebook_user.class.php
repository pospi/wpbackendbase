<?php
/**
 * Facebook user ID input
 *
 * Just a text input with some javascript wrapped around it to make the intent clearer
 *
 * @package wpBackendBase
 * @author Sam Pospischil <pospi@spadgos.com>
 */

class FormIOField_Facebook_user extends FormIOField_Text
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}">
		<label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label>
		<span class="facebook">
			<a target="_blank" href="http://www.facebook.com/{$group?groups/}%s">http://www.facebook.com/{$group?groups/}</a>
			<input type="text" name="{$name}" id="{$id}"{$value? value="$value"}{$readonly? readonly="readonly"} data-fio-type="facebook_user"{$validation? data-fio-validation="$validation"}{$dependencies? data-fio-depends="$dependencies"} />
		</span>
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';
}
