<?php

namespace dipl\Html\FormElement;

use dipl\Html\Attribute;

class SubmitElement extends InputElement
{
    protected $type = 'submit';

    protected $buttonLabel;

    public function setLabel($label)
    {
        $this->buttonLabel = $label;

        return $this;
    }

    /**
     * @return string
     */
    public function getButtonLabel()
    {
        if ($this->buttonLabel === null) {
            return $this->getName();
        } else {
            return $this->buttonLabel;
        }
    }

    /**
     * @return mixed|static
     */
    public function getValueAttribute()
    {
        return new Attribute('value', $this->getButtonLabel());
    }

    /**
     * @return bool
     */
    public function hasBeenPressed()
    {
        return $this->getButtonLabel() === $this->getValue();
    }

    public function isIgnored()
    {
        return true;
    }
}
