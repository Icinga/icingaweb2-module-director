<?php

namespace dipl\Web\Widget;

use dipl\Html\Container;

class Content extends Container
{
    protected $contentSeparator = "\n";

    protected $defaultAttributes = array('class' => 'content');
}
