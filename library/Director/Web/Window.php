<?php

namespace Icinga\Module\Director\Web;

use Icinga\Web\Window as WebWindow;

class Window extends WebWindow
{
    public function __construct($id)
    {
        parent::__construct(\preg_replace('/_.+$/', '', $id));
    }
}
