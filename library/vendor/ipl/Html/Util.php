<?php

namespace dipl\Html;

/**
 * @deprecated
 */
class Util
{
    /**
     * @deprecated
     */
    public static function escapeForHtml($value)
    {
        return Html::escapeForHtml($value);
    }

    /**
     * @deprecated
     */
    public static function renderError($error)
    {
        return Html::renderError($error);
    }

    /**
     * @deprecated
     */
    public static function showTraces($show = null)
    {
        return Html::showTraces($show);
    }

    /**
     * @deprecated
     */
    public static function wantHtml($any)
    {
        return Html::wantHtml($any);
    }

    /**
     * @deprecated
     */
    public static function canBeRenderedAsString($any)
    {
        return Html::canBeRenderedAsString($any);
    }

    /**
     * @deprecated
     */
    public static function getPhpTypeName($any)
    {
        return Html::getPhpTypeName($any);
    }
}
