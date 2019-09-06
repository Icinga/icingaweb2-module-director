<?php

namespace dipl\Html;

use ipl\Html\ValidHtml as iplValidHtml;

/**
 * Interface for HTML elements or primitives that promise to render valid UTF-8 encoded HTML5 with special characters
 * converted to HTML entities
 */
interface ValidHtml extends iplValidHtml
{
    /**
     * Render to HTML
     *
     * @return  string  UTF-8 encoded HTML5 with special characters converted to HTML entities
     */
    public function render();
}
