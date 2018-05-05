<?php

namespace dipl\Web\Widget;

use dipl\Html\Attributes;
use dipl\Html\BaseHtmlElement;
use dipl\Html\Html;
use dipl\Html\HtmlElement;

class AbstractList extends BaseHtmlElement
{
    protected $contentSeparator = "\n";

    /**
     * AbstractList constructor.
     * @param array $items
     * @param null $attributes
     */
    public function __construct(array $items = [], $attributes = null)
    {
        foreach ($items as $item) {
            $this->addItem($item);
        }

        if ($attributes !== null) {
            $this->addAttributes($attributes);
        }
    }

    /**
     * @param Html|array|string $content
     * @param Attributes|array $attributes
     *
     * @return $this
     */
    public function addItem($content, $attributes = null)
    {
        return $this->add(HtmlElement::create('li', $attributes, $content));
    }
}
