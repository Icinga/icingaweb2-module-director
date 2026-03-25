<?php

namespace Icinga\Module\Director\Web\Form\Element;

use ipl\Html\FormElement\SelectElement;
use ipl\I18n\Translation;

class IplBoolean extends SelectElement
{
    use Translation;

    public function __construct($name, $attributes = null)
    {
        parent::__construct($name, $attributes);

        $options = [
            'y'  => $this->translate('Yes'),
            'n'  => $this->translate('No'),
        ];
        if (! $this->isRequired()) {
            $options = [
                    null => $this->translate('- Please choose -'),
                ] + $options;
        }

        $this->setOptions($options);
    }

    public function setValue($value)
    {
        if ($value === 'y' || $value === true) {
            return parent::setValue('y');
        } elseif ($value === 'n' || $value === false) {
            return parent::setValue('n');
        }

        // Hint: this will fail
        return parent::setValue($value);
    }

    public function getValue()
    {
        if ($this->value === 'y') {
            return true;
        } elseif ($this->value === 'n') {
            return false;
        }

        return $this->value;
    }

    protected function isSelectedOption($optionValue): bool
    {
        $optionValue = match ($optionValue) {
            'y' => true,
            'n' => false,
            default => null
        };

        return parent::isSelectedOption(
            $optionValue
        );
    }
}
