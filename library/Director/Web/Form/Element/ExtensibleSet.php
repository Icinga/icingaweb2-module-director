<?php

namespace Icinga\Module\Director\Web\Form\Element;

use InvalidArgumentException;

/**
 * Input control for extensible sets
 */
class ExtensibleSet extends FormElement
{
    /**
     * Default form view helper to use for rendering
     * @var string
     */
    public $helper = 'formIplExtensibleSet';

   // private $multiOptions;

    public function getValue()
    {
        $value = parent::getValue();
        if (is_string($value) || is_numeric($value)) {
            $value = [$value];
        } elseif ($value === null) {
            return $value;
        }
        if (! is_array($value)) {
            throw new InvalidArgumentException(sprintf(
                'ExtensibleSet expects to work with Arrays, got %s',
                var_export($value, 1)
            ));
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

        parent::_filterValue($value, $key);
    }

    public function isValid($value, $context = null)
    {
        if ($value === null) {
            $value = [];
        }

        $value = array_filter($value, 'strlen');
        $this->setValue($value);
        if ($this->isRequired() && empty($value)) {
            // TODO: translate
            $this->addError('You are required to choose at least one element');
            return false;
        }

        if ($this->hasErrors()) {
            return false;
        }

        return parent::isValid($value, $context);
    }
}
