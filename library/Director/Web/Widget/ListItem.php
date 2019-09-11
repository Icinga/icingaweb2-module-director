<?php

namespace Icinga\Module\Director\Web\Widget;

use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\ValidHtml;

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
