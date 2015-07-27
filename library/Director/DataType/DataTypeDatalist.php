<?php

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Web\Hook\DataTypeHook;

class DataTypeDatalist extends DataTypeHook
{
    public function getFormElement($name, QuickForm $form)
    {
        $element = $form->createElement('select', $name);

        return $element;
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'datalist', array(
            'label'    => 'List name',
            'required' => true,
            'multiOptions' => array(
                null            => '- please choose -',
                'Foo'           => 'Dummy Foo',
                'Bar'           => 'Dummy Bar'
            ),
        ));
        return $form;
    }
}
