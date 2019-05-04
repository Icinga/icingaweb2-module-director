<?php

namespace dipl\Html;

use Exception;

class Text implements ValidHtml
{
    /** @var string */
    protected $string;

    protected $escaped = false;

    /**
     * Text constructor.
     *
     * @param string $string
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
            return Html::escape($this->string);
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->render();
        } catch (Exception $e) {
            return Error::render($e);
        }
    }
}
