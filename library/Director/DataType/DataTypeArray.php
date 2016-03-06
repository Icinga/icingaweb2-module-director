<?php

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class DataTypeArray extends DataTypeHook
{
    public function getFormElement($name, QuickForm $form)
    {
        return $form->createElement('extensibleSet', $name);
    }
}
