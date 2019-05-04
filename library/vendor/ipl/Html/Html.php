<?php

namespace dipl\Html;

use InvalidArgumentException;

/**
 * Class Html
 *
 * This is your main utility class when working with ipl\Html
 *
 * @package ipl\Html
 */
abstract class Html
{
    /**
     * Create a HTML element from the given tag, attributes and content
     *
     * This method does not render the HTML element but creates a {@link HtmlElement}
     * instance from the given tag, attributes and content
     *
     * @param   string $name       The desired HTML tag name
     * @param   mixed  $attributes HTML attributes or content for the element
     * @param   mixed  $content    The content of the element if no attributes have been given
     *
     * @return  HtmlElement The created element
     */
    public static function tag($name, $attributes = null, $content = null)
    {
        if ($attributes instanceof ValidHtml
            || is_string($attributes)
            || is_int($attributes)
            || is_float($attributes)
        ) {
            $content = $attributes;
            $attributes = null;
        } elseif (is_array($attributes)) {
            reset($attributes);
            if (is_int(key($attributes))) {
                $content = $attributes;
                $attributes = null;
            }
        }

        return new HtmlElement($name, $attributes, $content);
    }

    /**
     * Convert special characters to HTML5 entities using the UTF-8 character
     * set for encoding
     *
     * This method internally uses {@link htmlspecialchars} with the following
     * flags:
     *
     * * Single quotes are not escaped (ENT_COMPAT)
     * * Uses HTML5 entities, disallowing &#013; (ENT_HTML5)
     * * Invalid characters are replaced with � (ENT_SUBSTITUTE)
     *
     * Already existing HTML entities will be encoded as well.
     *
     * @param   string  $content        The content to encode
     *
     * @return  string  The encoded content
     */
    public static function escape($content)
    {
        return htmlspecialchars($content, ENT_COMPAT | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * sprintf()-like helper method
     *
     * This allows to use sprintf with ValidHtml elements, but with the
     * advantage that they'll not be rendered immediately. The result is an
     * instance of FormattedString, being ValidHtml
     *
     * Usage:
     *
     *     echo Html::sprintf('Hello %s!', Html::tag('strong', $name));
     *
     * @param $string
     * @return FormattedString
     */
    public static function sprintf($string)
    {
        $args = func_get_args();
        array_shift($args);

        return new FormattedString($string, $args);
    }

    /**
     * Accept any input and try to convert it to ValidHtml
     *
     * Returns the very same element in case it's already valid
     *
     * @param $any
     * @return ValidHtml
     * @throws InvalidArgumentException
     */
    public static function wantHtml($any)
    {
        if ($any instanceof ValidHtml) {
            return $any;
        } elseif (static::canBeRenderedAsString($any)) {
            return new Text($any);
        } elseif (is_array($any)) {
            $html = new HtmlDocument();
            foreach ($any as $el) {
                $html->add(static::wantHtml($el));
            }

            return $html;
        } else {
            throw new InvalidArgumentException(sprintf(
                'String, Html Element or Array of such expected, got "%s"',
                Error::getPhpTypeName($any)
            ));
        }
    }

    /**
     * Whether a given variable can be rendered as a string
     *
     * @param $any
     * @return bool
     */
    public static function canBeRenderedAsString($any)
    {
        return is_string($any) || is_int($any) || is_null($any) || is_float($any);
    }

    /**
     * @param $name
     * @param $arguments
     * @return HtmlElement
     */
    public static function __callStatic($name, $arguments)
    {
        $attributes = array_shift($arguments);
        $content = array_shift($arguments);

        return static::tag($name, $attributes, $content);
    }

    /**
     * @deprecated Use {@link Html::encode()} instead
     */
    public static function escapeForHtml($content)
    {
        return static::escape($content);
    }

    /**
     * @deprecated Use {@link Error::render()} instead
     */
    public static function renderError($error)
    {
        return Error::render($error);
    }
}
