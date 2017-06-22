<?php

namespace ipl\Web\Component;

use ipl\Html\Attributes;
use ipl\Html\BaseElement;
use ipl\Html\Element;
use ipl\Html\Html;

class AbstractList extends BaseElement
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
        return $this->add(Element::create('li', $attributes, $content));
    }
}
