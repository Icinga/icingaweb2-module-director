<?php

namespace dipl\Zf1;

use dipl\Html\Error;
use dipl\Web\Widget\Content;
use dipl\Web\Widget\Controls;
use Icinga\Application\Icinga;
use dipl\Html\ValidHtml;
use Zend_Controller_Action_Helper_Abstract as Helper;
use Zend_Controller_Action_HelperBroker as HelperBroker;

class SimpleViewRenderer extends Helper implements ValidHtml
{
    private $disabled = false;

    private $rendered = false;

    /** @var \Zend_View_Interface */
    public $view;

    public function disable($disabled = true)
    {
        $this->disabled = $disabled;
        return $this;
    }

    public function init()
    {
        // Register view with action controller (unless already registered)
        if ((null !== $this->_actionController) && (null === $this->_actionController->view)) {
            $this->_actionController->view = $this->view;
        }
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
        // Compat.
        if (isset($this->_actionController)
            && get_class($this->_actionController) === 'Icinga\\Controllers\\ErrorController'
        ) {
            $html = $this->simulateErrorController();
        } else {
            $html = '';
            if (null !== $this->view->controls) {
                $html .= $this->view->controls->__toString();
            }

            if (null !== $this->view->content) {
                $html .= $this->view->content->__toString();
            }
        }

        $this->getResponse()->appendBody($html, $name);
        // $this->setNoRender();
        $this->rendered = true;
    }

    protected function simulateErrorController()
    {
        $errorHandler = $this->_actionController->getParam('error_handler');
        if (isset($errorHandler->exception)) {
            $error = Error::show($errorHandler->exception);
        } else {
            $error = 'An unknown error occured';
        }

        /** @var \Icinga\Web\Request $request */
        $request = $this->getRequest();
        $controls = new Controls();
        $controls->getTabs()->add('error', [
            'label' => t('Error'),
            'url' => $request->getUrl(),
        ])->activate('error');
        $content = new Content();
        $content->add($error);

        return $controls . $content;
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
        // TODO: This is wrong, should be 'viewRenderer' - but that would
        //       currently break nearly everything, starting with full layout
        //       rendering
        return 'ViewRenderer';
    }
}
