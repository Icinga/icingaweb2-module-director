<?php

namespace Icinga\Module\Director\Web\Form\Element;

use Zend_Form_Element_Select as ZfSelect;

/**
 * Input control for booleans
 */
class Boolean extends ZfSelect
{
    public $options = array(
        null => '- please choose -',
        'y'  => 'Yes',
        'n'  => 'No',
    );

    public function getValue()
    {
        $value = $this->getUnfilteredValue();

        if ($value === 'y' || $value === true) {
            return true;
        } elseif ($value === 'n' || $value === false) {
            return false;
        }

        return null;
    }

    public function isValid($value, $context = null)
    {
        return $value === 'y'
            || $value === 'n'
            || $value === null
            || $value === true
            || $value === false;
    }

    public function setValue($value)
    {
        if ($value === true) {
            $value = 'y';
        } elseif ($value === false) {
            $value = 'n';
        }

        return parent::setValue($value);
    }

    protected function _translateOption($option, $value)
    {
        if (!isset($this->_translated[$option]) && !empty($value)) {
            $this->options[$option] = mt('director', $value);
            if ($this->options[$option] === $value) {
                return false;
            }
            $this->_translated[$option] = true;
            return true;
        }

        return false;
    }
}
