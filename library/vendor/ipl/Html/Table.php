<?php

namespace dipl\Html;

use Traversable;

class Table extends BaseHtmlElement
{
    protected $contentSeparator = ' ';

    /** @var string */
    protected $tag = 'table';

    /** @var HtmlElement */
    private $caption;

    /** @var HtmlElement */
    private $header;

    /** @var HtmlElement */
    private $body;

    /** @var HtmlElement */
    private $footer;

    /** @var array */
    private $columnsToBeRendered;

    /**
     * Return additional class names for a given row
     *
     * Extend this method in case you want to add classes to the table row
     * element (tr) based on a row's properties
     *
     * @return null|string|array
     */
    public function getRowClasses($row)
    {
        return null;
    }

    /**
     * Set the table title
     *
     * Will be rendered as a "caption" HTML element
     *
     * @param $content
     * @return $this
     */
    public function setCaption($content)
    {
        $this->caption = Html::tag('caption')->add(
            $content
        );

        return $this;
    }

    /**
     * Static helper creating a tr element
     *
     * @param Attributes|array $attributes
     * @param Html|array|string $content
     * @return HtmlElement
     */
    public static function tr($content = null, $attributes = null)
    {
        return Html::tag('tr', $attributes, $content);
    }

    /**
     * Static helper creating a th element
     *
     * @param Attributes|array $attributes
     * @param Html|array|string $content
     * @return HtmlElement
     */
    public static function th($content = null, $attributes = null)
    {
        return HtmlElement::create('th', $attributes, $content);
    }

    /**
     * Static helper creating a td element
     *
     * @param Attributes|array $attributes
     * @param Html|array|string $content
     * @return HtmlElement
     */
    public static function td($content = null, $attributes = null)
    {
        return HtmlElement::create('td', $attributes, $content);
    }

    /**
     * @param $row
     * @param null $attributes
     * @param string $tag
     * @return HtmlElement
     */
    public static function row($row, $attributes = null, $tag = 'td')
    {
        $tr = static::tr();
        foreach ((array) $row as $value) {
            $tr->add(Html::tag($tag, null, $value));
        }

        if ($attributes !== null) {
            $tr->setAttributes($attributes);
        }

        return $tr;
    }

    /**
     * @return HtmlElement
     */
    public function generateHeader()
    {
        return $this->nextHeader()->add(
            $this->addHeaderColumnsTo(static::tr())
        );
    }

    /**
     * @return HtmlElement
     */
    public function generateFooter()
    {
        return HtmlElement::create('tfoot')->add(
            $this->addHeaderColumnsTo(static::tr())
        );
    }

    /**
     * @param HtmlElement $parent
     * @return HtmlElement
     */
    protected function addHeaderColumnsTo(HtmlElement $parent)
    {
        foreach ($this->getColumnsToBeRendered() as $column) {
            $parent->add(
                Html::tag('th')->setContent($column)
            );
        }

        return $parent;
    }

    /**
     * @return null|array
     */
    public function getColumnsToBeRendered()
    {
        return $this->columnsToBeRendered;
    }

    /**
     * @deprecated
     * @param array $columns
     * @return $this
     */
    public function setColumnsToBeRendered(array $columns)
    {
        $this->columnsToBeRendered = $columns;

        return $this;
    }

    /**
     * @deprecated
     * @param $row
     * @return HtmlElement
     */
    public function renderRow($row)
    {
        $tr = $this->addRowClasses(Html::tag('tr'), $row);

        $columns = $this->getColumnsToBeRendered();
        if ($columns === null) {
            $this->setColumnsToBeRendered(array_keys((array) $row));
            $columns = $this->getColumnsToBeRendered();
        }

        foreach ($columns as $column) {
            $td = static::td();
            if (property_exists($row, $column)) {
                $td->setContent($row->$column);
            }
            $tr->add($td);
        }

        return $tr;
    }

    /**
     * @deprecated
     * @param HtmlElement $tr
     * @param $row
     * @return HtmlElement
     */
    public function addRowClasses(HtmlElement $tr, $row)
    {
        $classes = $this->getRowClasses($row);
        if (! empty($classes)) {
            $tr->getAttributes()->add('class', $classes);
        }

        return $tr;
    }

    /**
     * @deprecated
     * @param Traversable $rows
     * @return HtmlDocument|HtmlElement
     */
    public function renderRows(Traversable $rows)
    {
        $body = $this->body();
        foreach ($rows as $row) {
            $body->add($this->renderRow($row));
        }

        return $body;
    }

    /**
     * @return HtmlElement
     */
    public function body()
    {
        if ($this->body === null) {
            $this->body = Html::tag('tbody')->setSeparator("\n");
        }

        return $this->body;
    }

    /**
     * @return HtmlElement
     */
    public function header()
    {
        if ($this->header === null) {
            $this->header = Html::tag('thead')->setSeparator("\n");
        }

        return $this->header;
    }

    /**
     * @return HtmlElement
     */
    public function footer()
    {
        if ($this->footer === null) {
            $this->footer = $this->generateFooter();
        }

        return $this->footer;
    }

    /**
     * @return HtmlElement
     */
    public function nextBody()
    {
        if ($this->body !== null) {
            $this->add($this->body);
            $this->body = null;
        }

        return $this->body();
    }

    /**
     * @return HtmlElement
     */
    public function nextHeader()
    {
        if ($this->header !== null) {
            $this->add($this->header);
            $this->header = null;
        }

        return $this->header();
    }

    /**
     * @return string
     */
    public function renderContent()
    {
        if (null !== $this->caption) {
            $this->add($this->caption);
        }

        if (null !== $this->header) {
            $this->add($this->header);
        }

        if (null !== $this->body) {
            $this->add($this->body());
        }

        if (null !== $this->footer) {
            $this->add($this->footer);
        }

        return parent::renderContent();
    }
}
