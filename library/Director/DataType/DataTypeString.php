<?php

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class DataTypeString extends DataTypeHook
{
    public function getFormElement($name, QuickForm $form)
    {
        if ($this->getSetting('visibility', 'visible') === 'visible') {
            $element = $form->createElement('text', $name);
        } else {
            $element = $form->createElement('storedPassword', $name);
        }

        return $element;
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'visibility', [
            'label' => $form->translate('Visibility'),
            'multiOptions' => $form->optionalEnum([
                'visible' => $form->translate('Visible'),
                'hidden'  => $form->translate('Hidden'),
            ]),
            'value'    => 'visible',
            'required' => true,
        ]);

        return $form;
    }
}
