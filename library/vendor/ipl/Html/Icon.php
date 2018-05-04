<?php

namespace dipl\Html;

class Icon extends BaseHtmlElement
{
    protected $tag = 'i';

    public function __construct($name, $attributes = null)
    {
        $this->setAttributes($attributes);
        $this->getAttributes()->add('class', array('icon', 'icon-' . $name));
    }

    /**
     * @param string $name
     * @param array $attributes
     *
     * @return static
     */
    public static function create($name, array $attributes = null)
    {
        return new static($name, $attributes);
    }
}
