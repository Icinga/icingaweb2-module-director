<?php

namespace ipl\Html;

use Exception;

class Text implements ValidHtml
{
    /** @var string */
    protected $string;

    protected $escaped = false;

    /**
     * Text constructor.
     *
     * @param $text
     */
    public function __construct($string)
    {
        $this->string = (string) $string;
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->string;
    }

    /**
     * @param bool $escaped
     * @return $this
     */
    public function setEscaped($escaped = true)
    {
        $this->escaped = $escaped;
        return $this;
    }

    /**
     * @param $text
     *
     * @return static
     */
    public static function create($text)
    {
        return new static($text);
    }

    /**
     * @return string
     */
    public function render()
    {
        if ($this->escaped) {
            return $this->string;
        } else {
            return Util::escapeForHtml($this->string);
        }
    }

    /**
     * TODO: Allow to (statically) inject an error renderer. This will allow
     *       us to satisfy "Show exceptions" settings and/or preferences
     *
     * @param Exception|string $error
     * @return string
     */
    protected function renderError($error)
    {
        return Util::renderError($error);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->render();
        } catch (Exception $e) {
            return $this->renderError($e);
        }
    }
}
