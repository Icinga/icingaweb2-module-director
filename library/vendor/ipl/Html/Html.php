<?php

namespace dipl\Html;

use Countable;
use Exception;
use Icinga\Exception\IcingaException;
use Icinga\Exception\ProgrammingError;

/**
 * Class Html
 * @package dipl\Html
 */
class Html implements ValidHtml, Countable
{
    /** Charset to be used - we only support UTF-8 */
    const CHARSET = 'UTF-8';

    /** @var int The flags we use for htmlspecialchars depend on our PHP version */
    protected static $htmlEscapeFlags;

    /** @var bool */
    protected static $showTraces = true;

    protected $contentSeparator = '';

    /** @var ValidHtml[] */
    private $content = [];

    /** @var array */
    private $contentIndex = [];

    /**
     * @param ValidHtml|array|string $content
     * @return $this
     */
    public function add($content)
    {
        if (is_array($content)) {
            foreach ($content as $c) {
                $this->addContent($c);
            }
        } else {
            $this->addIndexedContent(Html::wantHtml($content));
        }

        return $this;
    }

    /**
     * @param $tag
     * @return BaseElement
     * @throws ProgrammingError
     */
    public function getFirst($tag)
    {
        foreach ($this->content as $c) {
            if ($c instanceof BaseElement && $c->getTag() === $tag) {
                return $c;
            }
        }

        throw new ProgrammingError(
            'Trying to get first %s, but there is no such',
            $tag
        );
    }

    /**
     * @param $content
     * @return $this
     */
    public function prepend($content)
    {
        if (is_array($content)) {
            foreach (array_reverse($content) as $c) {
                $this->prepend($c);
            }
        } else {
            $pos = 0;
            $html = Html::wantHtml($content);
            array_unshift($this->content, $html);
            $this->incrementIndexKeys();
            $this->addObjectPosition($html, $pos);
        }

        return $this;
    }

    public function remove(Html $html)
    {
        $key = spl_object_hash($html);
        if (array_key_exists($key, $this->contentIndex)) {
            foreach ($this->contentIndex[$key] as $pos) {
                unset($this->content[$pos]);
            }
        }

        $this->reIndexContent();
    }

    /**
     * @param $string
     * @return Html
     */
    public function addPrintf($string)
    {
        $args = func_get_args();
        array_shift($args);

        return $this->add(
            new FormattedString($string, $args)
        );
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
     * @param Html|array|string $content
     * @return $this
     */
    public function setContent($content)
    {
        $this->content = array();
        static::addContent($content);

        return $this;
    }

    /**
     * @see Html::add()
     */
    public function addContent($content)
    {
        return $this->add($content);
    }

    /**
     * return ValidHtml[]
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @return bool
     */
    public function hasContent()
    {
        return ! empty($this->content);
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->content);
    }

    /**
     * @param $separator
     * @return self
     */
    public function setSeparator($separator)
    {
        $this->contentSeparator = $separator;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        $html = array();

        foreach ($this->content as $element) {
            if (is_string($element)) {
                var_dump($this->content);
            }
            $html[] = $element->render();
        }

        return implode($this->contentSeparator, $html);
    }

    /**
     * @param $tag
     * @param null $attributes
     * @param null $content
     * @return BaseElement
     */
    public static function tag($tag, $attributes = null, $content = null)
    {
        return Element::create($tag, $attributes, $content);
    }

    /**
     * @deprecated
     * @param $name
     * @param null $attributes
     * @return Element
     * @throws ProgrammingError
     */
    public static function element($name, $attributes = null)
    {
        // TODO: This might be anything here, add a better check
        if (! ctype_alnum($name)) {
            throw new ProgrammingError('Invalid element requested');
        }

        $class = __NAMESPACE__ . '\\' . $name;
        /** @var Element $element */
        $element = new $class();
        if ($attributes !== null) {
            $element->setAttributes($attributes);
        }

        return $element;
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
            $html = new Html();
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
     * @return BaseElement
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

        return Element::create($name, $attributes, $content);
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
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->render();
        } catch (Exception $e) {
            return static::renderError($e);
        }
    }

    public static function sprintf($string)
    {
        $args = func_get_args();
        array_shift($args);
        return new FormattedString($string, $args);
    }

    private function reIndexContent()
    {
        $this->contentIndex = [];
        foreach ($this->content as $pos => $html) {
            $this->addObjectPosition($html, $pos);
        }
    }

    private function addObjectPosition(ValidHtml $html, $pos)
    {
        $key = spl_object_hash($html);
        if (array_key_exists($key, $this->contentIndex)) {
            $this->contentIndex[$key][] = $pos;
        } else {
            $this->contentIndex[$key] = [$pos];
        }
    }

    private function addIndexedContent(ValidHtml $html)
    {
        $pos = count($this->content);
        $this->content[$pos] = $html;
        $this->addObjectPosition($html, $pos);
    }

    private function incrementIndexKeys()
    {
        foreach ($this->contentIndex as & $index) {
            foreach ($index as & $pos) {
                $pos++;
            }
        }
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
}
