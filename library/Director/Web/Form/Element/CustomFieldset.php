<?php

namespace Icinga\Module\Director\Web\Form\Element;

use Zend_Form_Element;
use Zend_View_Interface;

// TODO: Still not completely implemented
class CustomFieldset extends FormElement
{
    public $helper = 'customFieldset';

    /** @var array will be set via options */
    protected $content = [];

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
        $this->content[] = $element;
    }

    public function addElements(array $elements)
    {
        foreach ($elements as $element) {
            $this->addElement($element);
        }
    }

    public function render(?Zend_View_Interface $view = null)
    {
//        $content = $this->getAttrib('elements');
//        var_dump($content);
//        $decorator = $this->getDecorator('Fieldset');
//        $this->getDecorator('Fieldset')->render();

        return parent::render($view);
    }
}
