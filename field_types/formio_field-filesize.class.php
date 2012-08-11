<?php
/**
 * Simple field class for displaying filesizes
 *
 * @package wpBackendBase
 * @author Sam Pospischil <pospi@spadgos.com>
 */

FormIO::preloadFieldClass('readonly');

class FormIOField_Filesize extends FormIOField_Readonly
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}">
		<label for="{$id}">{$desc}</label>
		<div class="readonly">{$value}</div>
		<input type="hidden" name="{$name}" id="{$id}"{$escapedvalue? value="$escapedvalue"} />
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';

	public static function formatBytes($byteVal)
	{
		$orders = array('bytes',
			'<abbr title="1 kibibyte = 2^10 bytes = 1024 bytes">KiB</abbr>',
			'<abbr title="1 mebibyte = 2^20 bytes = 1024 kibibytes = 1,048,576 bytes">MiB</abbr>',
			'<abbr title="1 gibibyte = 2^30 bytes = 1024 mebibytes = 1,073,741,824 bytes">GiB</abbr>',
		);

		$order = 0;
		while ($byteVal > 1024 && isset($orders[$order + 1])) {
			$byteVal = round($byteVal / 1024) + (($byteVal % 1024) / 1024);
			$order++;
		}

		$byteVal = round($byteVal, 2);

		return "$byteVal {$orders[$order]}";
	}

	protected function getBuilderVars()
	{
		$inputVars = parent::getBuilderVars();

		$inputVars['value'] = self::formatBytes($this->value);
		$inputVars['escapedvalue'] = htmlentities($this->value);

		return $inputVars;
	}
}
?>
