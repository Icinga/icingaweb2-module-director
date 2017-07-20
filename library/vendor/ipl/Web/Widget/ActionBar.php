<?php

namespace ipl\Web\Widget;

use ipl\Html\BaseElement;

class ActionBar extends BaseElement
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
        $this->attributes()->set('data-base-target', $target);
        return $this;
    }
}
