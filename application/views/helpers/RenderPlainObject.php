<?php

use Icinga\Module\Director\PlainObjectRenderer;

class Zend_View_Helper_RenderPlainObject extends Zend_View_Helper_Abstract
{
    public function renderPlainObject($object)
    {
        return PlainObjectRenderer::render($object);
    }
}
