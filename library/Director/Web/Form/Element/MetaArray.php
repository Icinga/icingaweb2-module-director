<?php

namespace Icinga\Module\Director\Web\Form\Element;

class MetaArray extends FormElement
{
    public $helper = 'formMetaArray';

    /**
     * Always ignore this element
     * @codingStandardsIgnoreStart
     *
     * @var boolean
     */
    protected $_ignore = true;
    // @codingStandardsIgnoreEnd

    public function isValid($values, $context = null)
    {
        if ($values === null) {
            return true;
        }

        $subElement = $this->getAttrib('subElement');

        foreach ($values as $value) {
            if ( ! $subElement->isValid($value)) {
                $this->addError(sprintf("'%s' is invalid", $value));
                return false;
            }
        }
        return true;
    }

    public function setValue($values) {
        $filteredValues = [];
        $subElement = $this->getAttrib('subElement');

        foreach ($values as $subValue) {
            $subValue = $subElement->setValue($subValue)->getValue();
            if ($subValue) {
                $filteredValues[] = $subValue;
            }
        }
        return parent::setValue($filteredValues);
    }

    public function getValue()
    {
        $value = parent::getValue();

        if (empty($value)) {
            return null;
        }
        return $value;
    }

    protected function _getErrorMessages()
    {
        return $this->getErrorMessages();
    }
}