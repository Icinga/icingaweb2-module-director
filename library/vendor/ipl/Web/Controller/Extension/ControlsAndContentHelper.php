<?php

namespace dipl\Web\Controller\Extension;

use dipl\Html\HtmlDocument;
use dipl\Web\Widget\Content;
use dipl\Web\Widget\Controls;
use dipl\Web\Widget\Tabs;
use dipl\Web\Url;
use Icinga\Web\Window;

trait ControlsAndContentHelper
{
    /** @var Controls */
    private $controls;

    /** @var Content */
    private $content;

    protected $title;

    /** @var Url */
    private $url;

    /** @var Url */
    private $originalUrl;

    /**
     * TODO: Not sure whether we need dedicated Content/Controls classes,
     *       a simple Container with a class name might suffice here
     *
     * @return Controls
     */
    public function controls()
    {
        if ($this->controls === null) {
            /** @var Window $window */
            $window = $this->Window();
            $id = $window->getId();
            if ($id === null || $id === Window::UNDEFINED) {
                $id = '_UNDEFINED_';
            }
            $this->view->controls = $this->controls = new Controls();
            $this->controls->addAttributes([
                'data-director-window-id' => $id,
            ]);
        }

        return $this->controls;
    }

    /**
     * @return Tabs
     */
    public function tabs(Tabs $tabs = null)
    {
        if ($tabs === null) {
            return $this->controls()->getTabs();
        } else {
            $this->controls()->setTabs($tabs);
            return $tabs;
        }
    }

    /**
     * @param HtmlDocument|null $actionBar
     * @return HtmlDocument
     */
    public function actions(HtmlDocument $actionBar = null)
    {
        if ($actionBar === null) {
            return $this->controls()->getActionBar();
        } else {
            $this->controls()->setActionBar($actionBar);
            return $actionBar;
        }
    }

    /**
     * @return Content
     */
    public function content()
    {
        if ($this->content === null) {
            $this->view->content = $this->content = new Content();
        }

        return $this->content;
    }

    /**
     * @param $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $this->makeTitle(func_get_args());
        return $this;
    }

    /**
     * @param $title
     * @return $this
     */
    public function addTitle($title)
    {
        $title = $this->makeTitle(func_get_args());
        $this->title = $title;
        $this->controls()->addTitle($title);

        return $this;
    }

    private function makeTitle($args)
    {
        $title = array_shift($args);

        if (empty($args)) {
            return $title;
        } else {
            return vsprintf($title, $args);
        }
    }

    /**
     * @param string $title
     * @param mixed $url
     * @param string $name
     * @return $this
     */
    public function addSingleTab($title, $url = null, $name = 'main')
    {
        if ($url === null) {
            $url = $this->url();
        }

        $this->tabs()->add($name, [
            'label' => $title,
            'url'   => $url,
        ])->activate($name);

        return $this;
    }

    /**
     * @return Url
     */
    public function url()
    {
        if ($this->url === null) {
            $this->url = $this->getOriginalUrl();
        }

        return $this->url;
    }

    /**
     * @return Url
     */
    public function getOriginalUrl()
    {
        if ($this->originalUrl === null) {
            $this->originalUrl = clone($this->getUrlFromRequest());
        }

        return clone($this->originalUrl);
    }

    /**
     * @return Url
     */
    protected function getUrlFromRequest()
    {
        /** @var \Icinga\Web\Request $request */
        $request = $this->getRequest();
        $webUrl = $request->getUrl();

        return Url::fromPath(
            $webUrl->getPath()
        )->setParams($webUrl->getParams());
    }
}
