<?php

namespace Icinga\Module\Director\Web\Form\Element;

use Zend_Form_Element;
use Zend_View_Interface;

// TODO: Still not completely implemented
class FormFieldset extends FormElement
{
    public $helper = 'formFieldset';

    /** @var array will be set via options */
    protected $elements = [];

    public function isValid($value, $context = null)
    {
        return true;
    }

    public function setValue($value)
    {
        return parent::setValue($value);
    }

    public function addElement(Zend_Form_Element $element)
    {
        $this->elements[] = $element;
    }

    public function addElements(array $elements)
    {
        foreach ($elements as $element) {
            $this->addElement($element);
        }
    }

    public function render(?Zend_View_Interface $view = null)
    {
        return parent::render($view);
    }
}
