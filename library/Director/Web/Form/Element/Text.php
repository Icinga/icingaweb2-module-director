<?php

namespace Icinga\Module\Director\Web\Form\Element;

use Zend_Form_Element_Text as ZfText;

class Text extends ZfText
{
    public function setValue($value)
    {
        if (\is_array($value)) {
            $value = \json_encode($value);
        }
        return parent::setValue((string) $value);
    }
}
