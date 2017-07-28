<?php

namespace ipl\Web\Widget;

use ipl\Html\BaseElement;
use ipl\Html\Container;
use ipl\Html\Html;

class Controls extends Container
{
    protected $contentSeparator = "\n";

    protected $defaultAttributes = array('class' => 'controls');

    /** @var Tabs */
    private $tabs;

    /** @var ActionBar */
    private $actions;

    /** @var string */
    private $title;

    /** @var string */
    private $subTitle;

    /** @var BaseElement */
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

    public function setTitleElement(BaseElement $element)
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
                $this->tabs->add($name, $tab);
            }
        }

        return $this;
    }

    /**
     * @return Html
     */
    public function getActionBar()
    {
        if ($this->actions === null) {
            $this->setActionBar(new ActionBar());
        }

        return $this->actions;
    }

    public function setActionBar(Html $actionBar)
    {
        if ($this->actions !== null) {
            $this->remove($this->actions);
        }

        $this->actions = $actionBar;
        $this->add($actionBar);

        return $this;
    }

    /**
     * @return BaseElement
     */
    protected function renderTitleElement()
    {
        $h1 = Html::tag('h1')->setContent($this->title);
        if ($this->subTitle) {
            $h1->setSeparator(' ')->add(
                Html::tag('small', null, $this->subTitle)
            );
        }

        return $h1;
    }

    public function renderContent()
    {
        if (null !== $this->tabs) {
            $this->prepend($this->tabs);
        }

        return parent::renderContent();
    }
}
