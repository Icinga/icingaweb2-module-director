<?php

namespace ipl\Zf1;

use Icinga\Application\Icinga;
use ipl\Html\ValidHtml;
use Zend_Controller_Action_Helper_Abstract as Helper;
use Zend_Controller_Action_HelperBroker as HelperBroker;

class SimpleViewRenderer extends Helper implements ValidHtml
{
    private $disabled = false;

    private $rendered = false;

    public $view;

    public function disable($disabled = true)
    {
        $this->disabled = $disabled;
        return $this;
    }

    public function replaceZendViewRenderer()
    {
        /** @var \Zend_Controller_Action_Helper_ViewRenderer $viewRenderer */
        $viewRenderer = Icinga::app()->getViewRenderer();
        $viewRenderer->setNeverRender();
        $viewRenderer->setNeverController();
        HelperBroker::removeHelper('viewRenderer');
        HelperBroker::addHelper($this);
        $this->view = $viewRenderer->view;
        return $this;
    }

    public function render($action = null, $name = null, $noController = null)
    {
        if (null === $name) {
            $name = null; // $this->getResponseSegment();
        }

        $this->getResponse()->appendBody(
            $this->view->controls . $this->view->content,
            $name
        );

        // $this->setNoRender();
        $this->rendered = true;
    }

    public function shouldRender()
    {
        return ! $this->disabled && ! $this->rendered;
    }

    public function postDispatch()
    {
        if ($this->shouldRender()) {
            $this->render();
        }
    }

    public function getName()
    {
        return 'ViewRenderer';
    }
}
