<?php

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Web\Form\Decorator\ViewHelperRaw;
use Icinga\Module\Director\Web\Form\QuickForm;
use Zend_Form_Element as ZfElement;

class DataTypeBoolean extends DataTypeHook
{
    public function getFormElement($name, QuickForm $form)
    {
        return $this->applyRawViewHelper(
            $form->createElement('boolean', $name)
        );
    }

    protected function applyRawViewHelper(ZfElement $element)
    {
        $vhClass = 'Zend_Form_Decorator_ViewHelper';
        $decorators = $element->getDecorators();
        if (array_key_exists($vhClass, $decorators)) {
            $decorators[$vhClass] = new ViewHelperRaw();
            $element->setDecorators($decorators);
        }

        return $element;
    }
}
