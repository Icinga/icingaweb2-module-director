<?php

namespace ipl\Html;

abstract class BaseElement extends Html
{
    /** @var array You may want to set default attributes when extending this class */
    protected $defaultAttributes;

    /** @var Attributes */
    protected $attributes;

    /** @var string */
    protected $tag;

    /**
     * @return Attributes
     */
    public function attributes()
    {
        if ($this->attributes === null) {
            $default = $this->getDefaultAttributes();
            if (empty($default)) {
                $this->attributes = new Attributes();
            } else {
                $this->attributes = Attributes::wantAttributes($default);
            }
        }

        return $this->attributes;
    }

    /**
     * @param Attributes|array|null $attributes
     * @return $this
     */
    public function setAttributes($attributes)
    {
        $this->attributes = Attributes::wantAttributes($attributes);
        return $this;
    }

    /**
     * @param Attributes|array|null $attributes
     * @return $this
     */
    public function addAttributes($attributes)
    {
        $this->attributes()->add($attributes);
        return $this;
    }

    public function getDefaultAttributes()
    {
        return $this->defaultAttributes;
    }

    public function setTag($tag)
    {
        $this->tag = $tag;
        return $this;
    }

    public function getTag()
    {
        return $this->tag;
    }

    /**
     * Container constructor.
     *
     * @param string $tag
     * @param Attributes|array $attributes
     *
     * @return Element
     */
    public function addElement($tag, $attributes = null)
    {
        $element = Element::create($tag, $attributes);
        $this->add($element);
        return $element;
    }

    public function renderContent()
    {
        return parent::render();
    }

    protected function assemble()
    {
    }

    /**
     * @return string
     */
    public function render()
    {
        $tag = $this->getTag();
        $this->assemble();
        $content = $this->renderContent();
        if (strlen($content) || $this->forcesClosingTag()) {
            return sprintf(
                '<%s%s>%s</%s>',
                $tag,
                $this->attributes()->render(),
                $content,
                $tag
            );
        } else {
            return sprintf(
                '<%s%s />',
                $tag,
                $this->attributes()->render()
            );
        }
    }

    public function forcesClosingTag()
    {
        return false;
    }

    /**
     * Whether the given something can be rendered
     *
     * @param mixed $any
     * @return bool
     */
    protected function canBeRendered($any)
    {
        return is_string($any) || is_int($any) || is_null($any);
    }
}
