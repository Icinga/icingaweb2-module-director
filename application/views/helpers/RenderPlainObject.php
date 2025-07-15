<?php

// Avoid complaints about missing namespace and invalid class name
// @codingStandardsIgnoreStart

use Icinga\Module\Director\PlainObjectRenderer;

class Zend_View_Helper_RenderPlainObject extends Zend_View_Helper_Abstract
// @codingStandardsIgnoreEnd
{
    public function renderPlainObject($object)
    {
        return PlainObjectRenderer::render($object);
    }
}
