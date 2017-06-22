<?php

namespace ipl\Web\Component;

use ipl\Html\BaseElement;

class ActionBar extends BaseElement
{
    protected $contentSeparator = ' ';

    /** @var string */
    protected $tag = 'div';

    protected $defaultAttributes = array('class' => 'action-bar');
}
