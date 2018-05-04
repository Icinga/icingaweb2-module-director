<?php

namespace dipl\Html;

use Exception;
use Icinga\Exception\IcingaException;

/**
 * TODO: This should no longer extend HtmlDocument
 */
class Html extends HtmlDocument
{
    /** Charset to be used - we only support UTF-8 */
    const CHARSET = 'UTF-8';

    /** @var int The flags we use for htmlspecialchars depend on our PHP version */
    protected static $htmlEscapeFlags;

    /** @var bool */
    protected static $showTraces = true;

    /**
     * @param $tag
     * @param null $attributes
     * @param null $content
     * @return HtmlElement
     */
    public static function tag($tag, $attributes = null, $content = null)
    {
        return HtmlElement::create($tag, $attributes, $content);
    }

    /**
     * @param $string
     * @return FormattedString
     * @throws IcingaException
     */
    public static function sprintf($string)
    {
        $args = func_get_args();
        array_shift($args);

        return new FormattedString($string, $args);
    }

    /**
     * Escape the given value top be safely used in view scripts
     *
     * @param  string $value  The output to be escaped
     * @return string
     */
    public static function escapeForHtml($value)
    {
        return htmlspecialchars(
            $value,
            static::htmlEscapeFlags(),
            self::CHARSET,
            true
        );
    }

    /**
     * @param $any
     * @return ValidHtml
     * @throws IcingaException
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
            // TODO: Should we add a dedicated Exception class?
            throw new IcingaException(
                'String, Html Element or Array of such expected, got "%s"',
                Html::getPhpTypeName($any)
            );
        }
    }

    public static function canBeRenderedAsString($any)
    {
        return is_string($any) || is_int($any) || is_null($any) || is_float($any);
    }

    /**
     * @param $any
     * @return string
     */
    public static function getPhpTypeName($any)
    {
        if (is_object($any)) {
            return get_class($any);
        } else {
            return gettype($any);
        }
    }

    /**
     * @param $name
     * @param $arguments
     * @return HtmlElement
     */
    public static function __callStatic($name, $arguments)
    {
        $attributes = array_shift($arguments);
        $content = null;
        if ($attributes instanceof ValidHtml || is_string($attributes)) {
            $content = $attributes;
            $attributes = null;
        } elseif (is_array($attributes)) {
            if (empty($attributes)) {
                $attributes = null;
            } elseif (is_int(key($attributes))) {
                $content = $attributes;
                $attributes = null;
            }
        }

        if (!empty($arguments)) {
            if (null === $content) {
                $content = $arguments;
            } else {
                $content = [$content, $arguments];
            }
        }

        return HtmlElement::create($name, $attributes, $content);
    }

    /**
     * @param Exception|string $error
     * @return string
     */
    public static function renderError($error)
    {
        if ($error instanceof Exception) {
            $file = preg_split('/[\/\\\]/', $error->getFile(), -1, PREG_SPLIT_NO_EMPTY);
            $file = array_pop($file);
            $msg = sprintf(
                '%s (%s:%d)',
                $error->getMessage(),
                $file,
                $error->getLine()
            );
        } elseif (is_string($error)) {
            $msg = $error;
        } else {
            $msg = 'Got an invalid error'; // TODO: translate?
        }

        $output = sprintf(
            // TODO: translate? Be careful when doing so, it must be failsafe!
            "<div class=\"exception\">\n<h1><i class=\"icon-bug\">"
            . "</i>Oops, an error occurred!</h1>\n<pre>%s</pre>\n",
            static::escapeForHtml($msg)
        );

        if (static::showTraces()) {
            $output .= sprintf(
                "<pre>%s</pre>\n",
                static::escapeForHtml($error->getTraceAsString())
            );
        }
        $output .= "</div>\n";
        return $output;
    }

    /**
     * @param null $show
     * @return bool|null
     */
    public static function showTraces($show = null)
    {
        if ($show !== null) {
            self::$showTraces = $show;
        }

        return self::$showTraces;
    }

    /**
     * This defines the flags used when escaping for HTML
     *
     * - Single quotes are not escaped (ENT_COMPAT)
     * - With PHP >= 5.4, invalid characters are replaced with � (ENT_SUBSTITUTE)
     * - With PHP 5.3 they are ignored (ENT_IGNORE, less secure)
     * - Uses HTML5 entities for PHP >= 5.4, disallowing &#013;
     *
     * @return int
     */
    protected static function htmlEscapeFlags()
    {
        if (self::$htmlEscapeFlags === null) {
            if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
                self::$htmlEscapeFlags = ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML5;
            } else {
                self::$htmlEscapeFlags = ENT_COMPAT | ENT_IGNORE;
            }
        }

        return self::$htmlEscapeFlags;
    }

    /**
     * @deprecated
     */
    public static function element($name, $attributes = null)
    {
        return Html::tag($name, $attributes);
    }

    /**
     * @deprecated
     * @see Html::add()
     */
    public function addContent($content)
    {
        return $this->add($content);
    }

    /**
     * @deprecated
     *
     * @return bool
     */
    public function hasContent()
    {
        return ! $this->isEmpty();
    }
}
