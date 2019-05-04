<?php

namespace dipl\Web\Widget;

use dipl\Html\BaseHtmlElement;

class Content extends BaseHtmlElement
{
    protected $tag = 'div';

    protected $contentSeparator = "\n";

    protected $defaultAttributes = ['class' => 'content'];
}
