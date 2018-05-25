<?php

namespace dipl\Html;

/**
 * The HtmlElement represents any HTML element
 *
 * A typical HTML element includes a tag, attributes and content.
 */
class HtmlElement extends BaseHtmlElement
{
    /**
     * Create a new HTML element from the given tag, attributes and content
     *
     * @param   string                  $tag        The tag for the element
     * @param   Attributes|array        $attributes The HTML attributes for the element
     * @param   ValidHtml|string|array  $content    The content of the element
     */
    public function __construct($tag, $attributes = null, $content = null)
    {
        $this->tag = $tag;

        if ($attributes !== null) {
            $this->getAttributes()->add($attributes);
        }

        if ($content !== null) {
            $this->setContent($content);
        }
    }

    /**
     * Container constructor.
     *
     * @param string $tag
     * @param Attributes|array $attributes
     * @param ValidHtml|array|string $content
     *
     * @return static
     */
    public static function create($tag, $attributes = null, $content = null)
    {
        return new static($tag, $attributes, $content);
    }
}
