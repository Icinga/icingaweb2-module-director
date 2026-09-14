<?php

namespace Icinga\Module\Director\Web\Form\Element;

use Zend_Form_Element_Select as ZfSelect;

/**
 * Input control for booleans
 */
class Boolean extends ZfSelect
{
    public $options = array(
        ''   => '- please choose -',
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
        if ($value === 'y' || $value === 'n') {
            $this->setValue($value);
            return true;
        }

        return parent::isValid($value, $context);
    }

    /**
     * @param string $value
     * @param string $key
     * @codingStandardsIgnoreStart
     */
    protected function _filterValue(&$value, &$key)
    {
        // @codingStandardsIgnoreEnd
        if ($value === true) {
            $value = 'y';
        } elseif ($value === false) {
            $value = 'n';
        } elseif ($value === '') {
            $value = null;
        }

        parent::_filterValue($value, $key);
    }

    public function setValue($value)
    {
        if ($value === true) {
            $value = 'y';
        } elseif ($value === false) {
            $value = 'n';
        } elseif ($value === '') {
            $value = null;
        }

        return parent::setValue($value);
    }

    /**
     * @codingStandardsIgnoreStart
     */
    protected function _translateOption($option, $value)
    {
        // @codingStandardsIgnoreEnd
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
