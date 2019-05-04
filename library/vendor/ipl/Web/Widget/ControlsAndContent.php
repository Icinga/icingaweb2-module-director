<?php

namespace dipl\Web\Widget;

use dipl\Html\HtmlDocument;
use dipl\Web\Url;

interface ControlsAndContent
{
    /**
     * @return Controls
     */
    public function controls();

    /**
     * @return Tabs
     */
    public function tabs();

    /**
     * @return HtmlDocument
     */
    public function actions(HtmlDocument $actionBar = null);

    /**
     * @return Content
     */
    public function content();

    /**
     * @param $title
     * @return $this
     */
    public function setTitle($title);

    /**
     * @param $title
     * @return $this
     */
    public function addTitle($title);

    /**
     * @param $title
     * @param null $url
     * @param string $name
     * @return $this
     */
    public function addSingleTab($title, $url = null, $name = 'main');

    /**
     * @return Url
     */
    public function url();

    /**
     * @return Url
     */
    public function getOriginalUrl();
}
