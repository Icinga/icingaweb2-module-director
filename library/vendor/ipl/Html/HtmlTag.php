<?php

namespace ipl\Html;

/**
 * @deprecated
 */
abstract class HtmlTag
{
    /**
     * @param $content
     * @param Attributes|array $attributes
     *
     * @return Element
     */
    public static function h1($content, $attributes = null)
    {
        return Element::create('h1', $attributes)->setContent($content);
    }

    /**
     * @param $content
     * @param Attributes|array $attributes
     *
     * @return Element
     */
    public static function p($content, $attributes = null)
    {
        return Element::create('p', $attributes)->setContent($content);
    }
}
