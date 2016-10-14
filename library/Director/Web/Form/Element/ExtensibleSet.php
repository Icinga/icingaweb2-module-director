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

    public function getValue()
    {
        $value = parent::getValue();
        if (! is_array($value)) {
            $value = array($value);
        }
        $value = array_filter($value, 'strlen');

        if (empty($value)) {
            return null;
        }

        return $value;
    }

    /**
     * We do not want one message per entry
     *
     * @codingStandardsIgnoreStart
     */
    protected function _getErrorMessages()
    {
        return $this->_errorMessages;
        // @codingStandardsIgnoreEnd
    }

    /**
     * @codingStandardsIgnoreStart
     */
    protected function _filterValue(&$value, &$key)
    {
        // @codingStandardsIgnoreEnd
        if (is_array($value)) {
            $value = array_filter($value, 'strlen');
        } elseif (is_string($value) && !strlen($value)) {
            $value = null;
        }

        return parent::_filterValue($value, $key);
    }

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
