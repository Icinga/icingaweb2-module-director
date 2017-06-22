<?php

namespace ipl\Html;

use Traversable;

class Table extends BaseElement
{
    protected $contentSeparator = ' ';

    /** @var string */
    protected $tag = 'table';

    /** @var Element */
    private $caption;

    /** @var Element */
    private $header;

    /** @var Element */
    private $body;

    /** @var Element */
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
        $this->caption = Element::create('caption')->addContent(
            $content
        );

        return $this;
    }

    /**
     * Static helper creating a tr element
     *
     * @param Attributes|array $attributes
     * @param Html|array|string $content
     * @return Element
     */
    public static function tr($content = null, $attributes = null)
    {
        return Element::create('tr', $attributes, $content);
    }

    /**
     * Static helper creating a th element
     *
     * @param Attributes|array $attributes
     * @param Html|array|string $content
     * @return Element
     */
    public static function th($content = null, $attributes = null)
    {
        return Element::create('th', $attributes, $content);
    }

    /**
     * Static helper creating a td element
     *
     * @param Attributes|array $attributes
     * @param Html|array|string $content
     * @return Element
     */
    public static function td($content = null, $attributes = null)
    {
        return Element::create('td', $attributes, $content);
    }


    public function generateHeader()
    {
        return Element::create('thead')->add(
            $this->addHeaderColumnsTo(static::tr())
        );
    }

    public function generateFooter()
    {
        return Element::create('tfoot')->add(
            $this->addHeaderColumnsTo(static::tr())
        );
    }

    protected function addHeaderColumnsTo(Element $parent)
    {
        foreach ($this->getColumnsToBeRendered() as $column) {
            $parent->add(
                Element::create('th')->setContent($column)
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

    public function setColumnsToBeRendered(array $columns)
    {
        $this->columnsToBeRendered = $columns;
        return $this;
    }

    public function renderRow($row)
    {
        $tr = $this->addRowClasses(Element::create('tr'), $row);

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

    public function addRowClasses(Element $tr, $row)
    {
        $classes = $this->getRowClasses($row);
        if (! empty($classes)) {
            $tr->attributes()->add('class', $classes);
        }

        return $tr;
    }

    public function renderRows(Traversable $rows)
    {
        $body = $this->body();
        foreach ($rows as $row) {
            $body->add($this->renderRow($row));
        }

        return $body;
    }

    public function body()
    {
        if ($this->body === null) {
            $this->body = Element::create('tbody')->setSeparator("\n");
        }

        return $this->body;
    }

    public function header()
    {
        if ($this->header === null) {
            $this->header = $this->generateHeader();
        }

        return $this->header;
    }

    public function footer()
    {
        if ($this->footer === null) {
            $this->footer = $this->generateFooter();
        }

        return $this->footer;
    }

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
