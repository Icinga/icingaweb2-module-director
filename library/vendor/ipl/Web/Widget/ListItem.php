<?php

namespace dipl\Web\Widget;

use dipl\Html\Attributes;
use dipl\Html\BaseHtmlElement;
use dipl\Html\Html;
use dipl\Html\ValidHtml;

class ListItem extends BaseHtmlElement
{
    protected $contentSeparator = "\n";

    /**
     * @param ValidHtml|array|string $content
     * @param Attributes|array $attributes
     *
     * @return $this
     */
    public function addItem($content, $attributes = null)
    {
        return $this->add(
            Html::tag('li', $attributes, $content)
        );
    }
}
