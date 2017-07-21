<?php

namespace ipl\Html;

use Exception;

/**
 * This class allows to have plain text passed in as a callback. That way it
 * would not be called and stringified unless it is going to be rendered and
 * escaped to HTML
 *
 * Usage
 * -----
 * <code>
 * $myVar = 'Some value';
 * $text = new DeferredText(function () use ($myVar) {
 *     return $myVar;
 * });
 * $myVar = 'Changed idea';
 * echo $text;
 * </code>
 */
class DeferredText implements ValidHtml
{
    /** @var callable will return the text that should be rendered */
    protected $callback;

    /** @var bool */
    protected $escaped = false;

    /**
     * DeferredText constructor.
     * @param callable $callback Must return the text that should be rendered
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Static factory
     *
     * @param callable $callback Must return the text that should be rendered
     * @return static
     */
    public static function create(callable $callback)
    {
        return new static($callback);
    }

    public function render()
    {
        $callback = $this->callback;

        if ($this->escaped) {
            return $callback();
        } else {
            return Util::escapeForHtml($callback());
        }
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
     * Calls the render function, but is failsafe. In case an Exception occurs,
     * an error is rendered instead of the expected HTML
     *
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->render();
        } catch (Exception $e) {
            return Util::renderError($e);
        }
    }
}
