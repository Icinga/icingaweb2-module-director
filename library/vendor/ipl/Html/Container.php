<?php

namespace ipl\Html;

class Container extends BaseElement
{
    /** @var string */
    protected $contentSeparator = "\n";

    /** @var string */
    protected $tag = 'div';

    protected function __construct()
    {
    }

    /**
     * @param Html|array|string $content
     * @param Attributes|array $attributes
     * @param string $tag
     *
     * @return static
     */
    public static function create($attributes = null, $content = null, $tag = null)
    {
        $container = new static();
        if ($content !== null) {
            $container->setContent($content);
        }

        if ($attributes !== null) {
            $container->setAttributes($attributes);
        }
        if ($tag !== null) {
            $container->setTag($tag);
        }

        return $container;
    }
}
