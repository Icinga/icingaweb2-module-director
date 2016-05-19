<?php

namespace Icinga\Module\Director\Web\Form\Decorator;

use Zend_Form_Decorator_ViewHelper as ViewHelper;
use Zend_Form_Element as Element;

class ViewHelperRaw extends ViewHelper
{
    public function getValue($element)
    {
        return $element->getUnfilteredValue();
    }
}
