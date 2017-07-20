<?php

namespace ipl\Html;

/**
 * Interface ValidHtml
 *
 * Implementations of this interface MUST guarantee, that the result of the
 * render() method gives valid UTF-8 encoded HTML5.
 */
interface ValidHtml
{
    /**
     * Renders to  HTML
     *
     * The result of this method is a valid UTF8-encoded HTML5 string.
     *
     * @return string
     */
    public function render();
}
