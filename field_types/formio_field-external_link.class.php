<?php

class FormIOField_External_link extends FormIOField_Group
{
	public function __construct($form, $name, $displayText = null, $defaultValue = null)
	{
		parent::__construct($form, $name, $displayText, $defaultValue);

		$this->setAttribute('classes', 'external-link');

		$f = $this->createSubField('text', 'name', 'Link name');
		$f->setAttribute('classes', 'name');
		$f = $this->createSubField('url', 'href', 'Link URL');
		$f->setAttribute('classes', 'href');
		$f = $this->createSubField('raw', '', '<div class="row wp-link-dlg-open"><input type="button" value="Select link" /></div>');
		$f = $this->createSubField('checkbox', 'newwin', 'Open in new window');
		$f->setAttribute('classes', 'target');
	}
}
