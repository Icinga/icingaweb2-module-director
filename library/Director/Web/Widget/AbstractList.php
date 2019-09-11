<?php

namespace Icinga\Module\Director\Web\Widget;

use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlElement;

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
