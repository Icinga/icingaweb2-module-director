<?php

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Web\Hook\DataTypeHook;

class DataTypeNumber extends DataTypeHook
{
    public function getFormElement(QuickForm $form)
    {
        $element = $form->createElement('text', 'foo', array(
            'label' => $this->translate('Number Element..'),
            'required'    => true,
        ));

        return $element;
    }

    public static function getFormat()
    {
        return 'json';
    }
}
