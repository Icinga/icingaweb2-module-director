<?php

namespace dipl\Web\Widget;

use dipl\Html\BaseHtmlElement;

class ActionBar extends BaseHtmlElement
{
    protected $contentSeparator = ' ';

    /** @var string */
    protected $tag = 'div';

    protected $defaultAttributes = array('class' => 'action-bar');

    /**
     * @param  string $target
     * @return $this
     */
    public function setBaseTarget($target)
    {
        $this->getAttributes()->set('data-base-target', $target);
        return $this;
    }
}
