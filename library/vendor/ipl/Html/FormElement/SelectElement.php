<?php

namespace dipl\Html\FormElement;

class SelectElement extends BaseFormElement
{
    protected $tag = 'select';

    /** @var SelectOption[] */
    protected $options = [];

    public function __construct($name, $attributes = null)
    {
        $this->getAttributes()->registerAttributeCallback(
            'options',
            null,
            [$this, 'setOptions']
        );
        parent::__construct($name, $attributes);
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options)
    {
        foreach ($options as $value => $label) {
            $this->options[$value] = new SelectOption($value, $label);
        }

        return $this;
    }

    protected function assemble()
    {
        $currentValue = $this->getValue();
        foreach ($this->options as $value => $option) {
            if ($value  == $currentValue) {
                $option->getAttributes()->set('selected', true);
            }

            $this->add($option);
        }
    }
}
