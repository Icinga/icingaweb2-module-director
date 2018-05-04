<?php

namespace dipl\Html;

/**
 * @deprecated
 */
abstract class BaseElement extends BaseHtmlElement
{
    /**
     * @deprecated
     */
    public function attributes()
    {
        return $this->getAttributes();
    }

    /**
     * Container constructor.
     *
     * @param string $tag
     * @param Attributes|array $attributes
     *
     * @deprecated
     *
     * @return HtmlElement
     */
    public function addElement($tag, $attributes = null)
    {
        $element = Html::tag($tag, $attributes);
        $this->add($element);

        return $element;
    }
}
