<?php

namespace ipl\Web\Component;

use ipl\Html\Attributes;
use ipl\Html\BaseElement;
use ipl\Html\Element;
use ipl\Html\Html;

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
