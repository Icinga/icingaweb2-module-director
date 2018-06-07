<?php

namespace dipl\Html\FormElement;

use dipl\Html\Attribute;

abstract class InputElement extends BaseFormElement
{
    protected $tag = 'input';

    /** @var string */
    protected $type;

    public function __construct($name, $attributes = null)
    {
        parent::__construct($name, $attributes);
        $this->getAttributes()->registerAttributeCallback('type', [$this, 'getTypeAttribute']);
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return Attribute
     */
    public function getTypeAttribute()
    {
        return new Attribute('type', $this->getType());
    }
}
