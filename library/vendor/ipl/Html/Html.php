<?php

namespace ipl\Html;

use Exception;
use Icinga\Exception\ProgrammingError;

class Html implements ValidHtml
{
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
            $this->addIndexedContent(Util::wantHtml($content));
        }

        return $this;
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
            $html = Util::wantHtml($content);
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
     * @param Html|array|string $content
     * @return self
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
     * @return Element
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

        if (! empty($arguments)) {
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
}
