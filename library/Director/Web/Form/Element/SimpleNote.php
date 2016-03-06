<?php

namespace Icinga\Module\Director\Web\Form\Element;

class SimpleNote extends FormElement
{
    public $helper = 'formSimpleNote';
    
    protected $_ignore = true;
    
    public function isValid($value, $context = null)
    {
        return true;
    }
}
