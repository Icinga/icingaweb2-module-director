<?php

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Data\ValueFilter\FilterInt;

class DataTypeNumber extends DataTypeHook
{
    public function getFormElement($name, QuickForm $form)
    {
        $element = $form->createElement('text', $name)
            ->addValidator('int')
            ->addFilter(new FilterInt());

        return $element;
    }
}
