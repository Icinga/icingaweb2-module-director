<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Import\SyncUtils;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierCondReplace extends PropertyModifierHook
{
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'replace_on', array(
            'label'        => $form->translate('Replacement condition'),
            'description'  => $form->translate('Under what condition should we replace the target value?'),
	    'multiOptions' => $form->optionalEnum(array(
		'missing' => $form->translate("Replace only if the target doesn't exist"),
		'null'    => $form->translate("Replace only if the target doesn't exist or is null"),
		'blank'   => $form->translate("Replace only if the target doesn't exist or is an empty string"),
		'empty'   => $form->translate("Replace only if the target exists and is an empty string (sets target to null if it doesn't exist)"),
		'false'   => $form->translate("Replace only if the target exists and is not a true value (sets target to null if it doesn't exist)"),
		'true'    => $form->translate("Replace only if the target exists and is a true value (sets target to null if it doesn't exist)"),
	    )),
            'required'     => true,
        ));
    }

    public function getName()
    {
        return 'Conditional replacement of a fields value';
    }

    public function requiresRow()
    {
        return true;
    }

    public function transform($value)
    {
	$target = $this->getTargetProperty();
	if (!$target) {
		throw new InvalidPropertyException(
		    "Target property is required for conditional replacement"
		);
	}
	$row = $this->getRow();
	if (! property_exists($row, $target)) {
	    $retult = null;
	    switch ($this->getSetting('replace_on')) {
		case 'missing':
		case 'null':
		case 'blank':
		    $result = $value;
	    }
	} else {
            $result = $row->$target;
	    switch ($this->getSetting('replace_on')) {
		case 'null':
		    if (is_null($result)) {
			$result = $value;
		    }
		    break;
		case 'blank':
		case 'empty':
		    if ($result == '') {
			$result = $value;
		    }
		    break;
		case 'false':
		    if (!$result) {
			$result = $value;
		    }
		    break;
		case 'true':
		    if ($result) {
			$result = $value;
		    }
		    break;
	    }
	}

	return $result;
    }
}
