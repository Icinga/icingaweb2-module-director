<?php

namespace dipl\Html\FormElement;

use dipl\Html\BaseHtmlElement;
use InvalidArgumentException;

trait FormElementContainer
{
    /** @var BaseFormElement[] */
    private $elements = [];

    /**
     * @return BaseFormElement[]
     */
    public function getElements()
    {
        return $this->elements;
    }

    /**
     * @param $name
     * @return BaseFormElement
     */
    public function getElement($name)
    {
        if (! array_key_exists($name, $this->elements)) {
            throw new InvalidArgumentException(sprintf(
                'Trying to get non existent element "%s"',
                $name
            ));
        }
        return $this->elements[$name];
    }

    /**
     * @param string|BaseFormElement $element
     * @return bool
     */
    public function hasElement($element)
    {
        if (is_string($element)) {
            return array_key_exists($element, $this->elements);
        } elseif ($element instanceof BaseFormElement) {
            return in_array($element, $this->elements, true);
        } else {
            return false;
        }
    }

    /**
     * @param string $name
     * @param string|BaseFormElement $type
     * @param array|null $options
     * @return $this
     */
    public function addElement($name, $type = null, $options = null)
    {
        $this->registerElement($name, $type, $options);

        if ($this instanceof BaseHtmlElement) {
            $element = $this->decorate($this->getElement($name));
            $this->add($element);
        }

        return $this;
    }

    protected function decorate(BaseFormElement $element)
    {
        if ($this->hasDefaultElementDecorator()) {
            $this->getDefaultElementDecorator()->wrap($element);
        }

        return $element;
    }

    /**
     * @param string $name
     * @param string|BaseFormElement $type
     * @param array|null $options
     * @return $this
     */
    public function registerElement($name, $type = null, $options = null)
    {
        if (is_string($type)) {
            $type = $this->createElement($name, $type, $options);
        }

        $this->elements[$name] = $type;

        if (method_exists($this, 'onRegisteredElement')) {
            $this->onRegisteredElement($name, $type);
        }

        return $this;
    }

    /**
     * TODO: Add PluginLoader
     *
     * @param $name
     * @param $type
     * @param $attributes
     * @return BaseFormElement
     */
    public function createElement($name, $type, $attributes = null)
    {
        $class = __NAMESPACE__ . '\\' . ucfirst($type) . 'Element';
        if (class_exists($class)) {
            /** @var BaseFormElement $element */
            $element = new $class($name);
            if ($attributes !== null) {
                $element->addAttributes($attributes);
            }

            return $element;
        } else {
            throw new InvalidArgumentException(sprintf(
                'Unable to create Form Element, no such type: %s',
                $type
            ));
        }
    }

    /**
     * @param FormElementContainer $form
     */
    public function addElementsFrom(FormElementContainer $form)
    {
        foreach ($form->getElements() as $name => $element) {
            $this->addElement($element);
        }
    }

    public function setDefaultElementDecorator(BaseHtmlElement $decorator)
    {
        $this->defaultElementDecorator = $decorator;

        return $this;
    }

    public function hasDefaultElementDecorator()
    {
        return $this->defaultElementDecorator !== null;
    }

    /**
     * @return BaseHtmlElement
     */
    public function getDefaultElementDecorator()
    {
        return $this->defaultElementDecorator;
    }
}
