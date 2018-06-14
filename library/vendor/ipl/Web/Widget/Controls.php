<?php

namespace dipl\Web\Widget;

use dipl\Html\BaseHtmlElement;
use dipl\Html\Html;
use dipl\Html\HtmlDocument;
use Icinga\Exception\ProgrammingError;
use RuntimeException;

class Controls extends BaseHtmlElement
{
    protected $tag = 'div';

    protected $contentSeparator = "\n";

    protected $defaultAttributes = ['class' => 'controls'];

    /** @var Tabs */
    private $tabs;

    /** @var ActionBar */
    private $actions;

    /** @var string */
    private $title;

    /** @var string */
    private $subTitle;

    /** @var BaseHtmlElement */
    private $titleElement;

    /**
     * @param $title
     * @param null $subTitle
     * @return $this
     */
    public function addTitle($title, $subTitle = null)
    {
        $this->title = $title;
        if ($subTitle !== null) {
            $this->subTitle = $subTitle;
        }

        return $this->setTitleElement($this->renderTitleElement());
    }

    /**
     * @param BaseHtmlElement $element
     * @return $this
     */
    public function setTitleElement(BaseHtmlElement $element)
    {
        if ($this->titleElement !== null) {
            $this->remove($this->titleElement);
        }

        $this->titleElement = $element;
        $this->prepend($element);

        return $this;
    }

    public function getTitleElement()
    {
        return $this->titleElement;
    }

    /**
     * @return Tabs
     */
    public function getTabs()
    {
        if ($this->tabs === null) {
            $this->tabs = new Tabs();
        }

        return $this->tabs;
    }

    /**
     * @param Tabs $tabs
     * @return $this
     */
    public function setTabs(Tabs $tabs)
    {
        $this->tabs = $tabs;
        return $this;
    }

    /**
     * @param Tabs $tabs
     * @return $this
     */
    public function prependTabs(Tabs $tabs)
    {
        if ($this->tabs === null) {
            $this->tabs = $tabs;
        } else {
            $current = $this->tabs->getTabs();
            $this->tabs = $tabs;
            foreach ($current as $name => $tab) {
                try {
                    // TODO: Use ipl-based tabs
                    $this->tabs->add($name, $tab);
                } catch (ProgrammingError $e) {
                    throw new RuntimeException($e->getMessage(), 0, $e);
                }
            }
        }

        return $this;
    }

    /**
     * @return ActionBar
     */
    public function getActionBar()
    {
        if ($this->actions === null) {
            $this->setActionBar(new ActionBar());
        }

        return $this->actions;
    }

    /**
     * @param HtmlDocument $actionBar
     * @return $this
     */
    public function setActionBar(HtmlDocument $actionBar)
    {
        if ($this->actions !== null) {
            $this->remove($this->actions);
        }

        $this->actions = $actionBar;
        $this->add($actionBar);

        return $this;
    }

    /**
     * @return BaseHtmlElement
     */
    protected function renderTitleElement()
    {
        $h1 = Html::tag('h1', null, $this->title);
        if ($this->subTitle) {
            $h1->setSeparator(' ')->add(
                Html::tag('small', null, $this->subTitle)
            );
        }

        return $h1;
    }

    /**
     * @return string
     */
    public function renderContent()
    {
        if (null !== $this->tabs) {
            $this->prepend($this->tabs);
        }

        return parent::renderContent();
    }
}
