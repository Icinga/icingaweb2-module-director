<?php

namespace dipl\Web\Widget;

use dipl\Html\Attributes;
use dipl\Html\BaseElement;
use dipl\Html\Element;
use dipl\Html\Html;

class ListItem extends BaseElement
{
    protected $contentSeparator = "\n";

    /**
     * @param Html|array|string $content
     * @param Attributes|array $attributes
     *
     * @return $this
     */
    public function addItem($content, $attributes = null)
    {
        return $this->add(
            Element::create('li', $content, $attributes)
        );
    }
}
