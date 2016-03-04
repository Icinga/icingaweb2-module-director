<?php

namespace Icinga\Module\Director\Web\Form\Element;

/**
 * Input control for extensible sets
 */
class ExtensibleSet extends FormElement
{
    /**
     * Default form view helper to use for rendering
     * @var string
     */
    public $helper = 'formExtensibleSet';

   // private $multiOptions;

    public function isValid($value, $context = null)
    {
        if ($value === null) {
            $value = array();
        }

        $value = array_filter($value, 'strlen');
        $this->setValue($value);
        if ($this->isRequired() && empty($value)) {
            // TODO: translate
            $this->addError('You are required to choose at least one element');
            return false;
        }

        return true;
    }
}
