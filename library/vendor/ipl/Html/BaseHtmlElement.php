<?php

namespace dipl\Html;

use RuntimeException;

abstract class BaseHtmlElement extends HtmlDocument
{
    /** @var array You may want to set default attributes when extending this class */
    protected $defaultAttributes;

    /** @var Attributes */
    protected $attributes;

    /** @var string */
    protected $tag;

    /**
     * List of void elements which must not contain end tags or content
     *
     * If {@link $isVoid} is null, this property should be used to decide whether the content and end tag has to be
     * rendered.
     *
     * @var array
     *
     * @see https://www.w3.org/TR/html5/syntax.html#void-elements
     */
    protected static $voidElements = [
        'area'   => 1,
        'base'   => 1,
        'br'     => 1,
        'col'    => 1,
        'embed'  => 1,
        'hr'     => 1,
        'img'    => 1,
        'input'  => 1,
        'link'   => 1,
        'meta'   => 1,
        'param'  => 1,
        'source' => 1,
        'track'  => 1,
        'wbr'    => 1
    ];

    /** @var bool|null Whether the element is void. If null, void check should use {@link $voidElements} */
    protected $isVoid;

    /**
     * Get the attributes of the element
     *
     * @return  Attributes
     */
    public function getAttributes()
    {
        if ($this->attributes === null) {
            $default = $this->getDefaultAttributes();
            if (empty($default)) {
                $this->attributes = new Attributes();
            } else {
                $this->attributes = Attributes::wantAttributes($default);
            }
        }

        return $this->attributes;
    }

    /**
     * Set the attributes of the element
     *
     * @param   Attributes|array|null   $attributes
     *
     * @return  $this
     */
    public function setAttributes($attributes)
    {
        $this->attributes = Attributes::wantAttributes($attributes);

        return $this;
    }

    /**
     * Set the attribute with the given name and value
     *
     * If the attribute with the given name already exists, it gets overridden.
     *
     * @param   string              $name   The name of the attribute
     * @param   string|bool|array   $value  The value of the attribute
     *
     * @return  $this
     */
    public function setAttribute($name, $value)
    {
        $this->getAttributes()->set($name, $value);

        return $this;
    }

    /**
     * Add the given attributes
     *
     * @param   Attributes|array    $attributes
     *
     * @return  $this
     */
    public function addAttributes($attributes)
    {
        $this->getAttributes()->add($attributes);

        return $this;
    }

    /**
     * Get the default attributes of the element
     *
     * @return  array
     */
    public function getDefaultAttributes()
    {
        return $this->defaultAttributes;
    }

    /**
     * Get the tag of the element
     *
     * Since HTML Elements must have a tag, this method throws an exception if the element does not have a tag.
     *
     * @return  string
     *
     * @throws  RuntimeException   If the element does not have a tag
     */
    final public function getTag()
    {
        $tag = $this->tag();

        if (! strlen($tag)) {
            throw new RuntimeException('Element must have a tag');
        }

        return $tag;
    }

    /**
     * Internal method for accessing the tag
     *
     * You may override this method in order to provide the tag dynamically
     *
     * @return  string
     */
    protected function tag()
    {
        return $this->tag;
    }

    /**
     * Set the tag of the element
     *
     * @param   string  $tag
     *
     * @return  $this
     */
    public function setTag($tag)
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * Render the content of the element to HTML
     *
     * @return  string
     */
    public function renderContent()
    {
        return parent::renderUnwrapped();
    }

    public function add($content)
    {
        $this->ensureAssembled();

        parent::add($content);

        return $this;
    }

    /**
     * @inheritdoc
     *
     * @throws  RuntimeException   If the element does not have a tag or is void but has content
     */
    public function renderUnwrapped()
    {
        $this->ensureAssembled();

        $tag = $this->getTag();
        $attributes = $this->getAttributes()->render();
        $content = $this->renderContent();

        if (! $this->wantsClosingTag()) {
            if (strlen($content)) {
                throw new RuntimeException('Void elements must not have content');
            }

            return sprintf('<%s%s />', $tag, $attributes);
        }

        return sprintf(
            '<%s%s>%s</%s>',
            $tag,
            $attributes,
            $content,
            $tag
        );
    }

    /**
     * Use this element to wrap the given document
     *
     * @param   HtmlDocument    $document
     *
     * @return  $this
     */
    public function wrap(HtmlDocument $document)
    {
        $document->addWrapper($this);

        return $this;
    }

    /**
     * Get whether the closing tag should be rendered
     *
     * @return  bool    True for void elements, false otherwise
     */
    public function wantsClosingTag()
    {
        // TODO: There is more. SVG and MathML namespaces
        return ! $this->isVoid();
    }

    /**
     * Get whether the element is void
     *
     * The default void detection which checks whether the element's tag is in the list of void elements according to
     * https://www.w3.org/TR/html5/syntax.html#void-elements.
     *
     * If you want to override this behavior, use {@link setVoid()}.
     *
     * @return  bool
     */
    public function isVoid()
    {
        if ($this->isVoid !== null) {
            return $this->isVoid;
        }

        $tag = $this->getTag();

        return isset(self::$voidElements[$tag]);
    }

    /**
     * Set whether the element is void
     *
     * You may use this method to override the default void detection which checks whether the element's tag is in the
     * list of void elements according to https://www.w3.org/TR/html5/syntax.html#void-elements.
     *
     * If you specify null, void detection is reset to its default behavior.
     *
     * @param   bool|null    $void
     *
     * @return  $this
     */
    public function setVoid($void = true)
    {
        $this->isVoid = $void === null ?: (bool) $void;

        return $this;
    }
}
