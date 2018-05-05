<?php

namespace dipl\Html;

/**
 * @deprecated
 */
abstract class HtmlTag
{
    /**
     * @param $content
     * @param Attributes|array $attributes
     *
     * @return HtmlElement
     */
    public static function h1($content, $attributes = null)
    {
        return HtmlElement::create('h1', $attributes)->setContent($content);
    }

    /**
     * @param $content
     * @param Attributes|array $attributes
     *
     * @return HtmlElement
     */
    public static function p($content, $attributes = null)
    {
        return HtmlElement::create('p', $attributes)->setContent($content);
    }
}
