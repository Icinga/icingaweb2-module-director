<?php

namespace ipl\Web\Component;

use ipl\Html\Html;
use ipl\Web\Url;

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
     * @return Html
     */
    public function actions(Html $actionBar = null);

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
