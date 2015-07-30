<?php

namespace Icinga\Module\Director\Web\Form;

use Icinga\Application\Icinga;
use Icinga\Application\Modules\Module;
use Icinga\Web\Notification;
use Icinga\Web\Request;
use Icinga\Web\Url;
use Zend_Form;

/**
 * QuickForm wants to be a base class for simple forms
 */
abstract class QuickForm extends Zend_Form
{
    const ID = '__FORM_NAME';

    const CSRF = '__FORM_CSRF';

    /**
     * The name of this form
     */
    protected $formName;

    /**
     * Whether the form has been sent
     */
    protected $hasBeenSent;

    /**
     * Whether the form has been sent
     */
    protected $hasBeenSubmitted;

    /**
     * The submit caption, element - still tbd
     */
    // protected $submit;

    /**
     * Our request
     */
    protected $request;

    protected $successUrl;

    protected $successMessage;

    protected $submitLabel;

    /**
     * Whether form elements have already been created
     */
    protected $didSetup = false;

    /**
     * The Icinga module this form belongs to. Usually only set if the
     * form is initialized through the FormLoader
     */
    protected $icingaModule;

    protected $icingaModuleName;

    public function __construct($options = null)
    {
        if ($options !== null && array_key_exists('icingaModule', $options)) {
            $this->icingaModule = $options['icingaModule'];
            $this->icingaModuleName = $this->icingaModule->getName();
            unset($options['icingaModule']);
        }
        parent::__construct($options);
        $this->setMethod('post');
        $this->setAction(Url::fromRequest());
        $this->createIdElement();
        $this->regenerateCsrfToken();
    }

    protected function addSubmitButtonIfSet()
    {
        if (false !== ($label = $this->getSubmitLabel())) {
            $this->addElement('submit', $label);
            $this->getElement($label)->setLabel($label);
        }
    }

    // TODO: This is ugly, we need to defer button creation
    protected function moveSubmitToBottom()
    {
        $label = $this->getSubmitLabel();
        if ($submit = $this->getElement($label)) {
            $this->removeElement($label);
            $this->addElement($submit);
        }
    }

    protected function createIdElement()
    {
        $this->detectName();
        $this->addHidden(self::ID, $this->getName());
        $this->getElement(self::ID)->setIgnore(true);
    }

    protected function getSentValue($name, $default = null)
    {
        $request = $this->getRequest();

        if ($request->isPost()) {
            return $request->getPost($name);
        } else {
            return $default;
        }
    }

    public function getSubmitLabel()
    {
        if ($this->submitLabel === null) {
            return $this->translate('Submit');
        }

        return $this->submitLabel;
    }

    public function setSubmitLabel($label)
    {
        $this->submitLabel = $label;
        return $this;
    }

    protected function loadForm($name, Module $module = null)
    {
        if ($module === null) {
            $module = $this->icingaModule;
        }

        return FormLoader::load($name, $module);
    }

    public function regenerateCsrfToken()
    {
        if (! $element = $this->getElement(self::CSRF)) {
            $this->addHidden(self::CSRF);
            $element = $this->getElement(self::CSRF);
        }
        $element->setValue(CsrfToken::generate())->setIgnore(true);
        return $this;
    }

    public function removeCsrfToken()
    {
        $this->removeElement(self::CSRF);
        return $this;
    }

    public function addHidden($name, $value = null)
    {
        $this->addElement('hidden', $name);
        $this->getElement($name)->setDecorators(array('ViewHelper'));
        if ($value !== null) {
            $this->setDefault($name, $value);
        }
        return $this;
    }

    public function setSuccessUrl($url)
    {
        $this->successUrl = $url;
        return $this;
    }

    public function setup()
    {
    }

    protected function onSetup()
    {
    }

    public function setAction($action)
    {
        if (! $action instanceof Url) {
            $action = Url::fromPath($action);
        }
        return parent::setAction((string) $action);
    }

    public function setIcingaModule(Module $module)
    {
        $this->icingaModule = $module;
        return $this;
    }

    public function hasBeenSubmitted()
    {
        if ($this->hasBeenSubmitted === null) {
            $req = $this->getRequest();
            if ($req->isPost()) {
                $post = $req->getPost();
                $label = $this->getSubmitLabel();
                if ($label === false) {
                    $this->hasBeenSubmitted = $this->hasBeenSent();
                } else {
                    $elementName = $this->getElement($label)->getName();
                    $this->hasBeenSubmitted = array_key_exists($elementName, $post)
                         && $post[$elementName] === $label;
                }
            } else {
                $this->hasBeenSubmitted === false;
            }
        }

        return $this->hasBeenSubmitted;
    }

    protected function beforeValidation($data = array())
    {
    }

    public function prepareElements()
    {
        if (! $this->didSetup) {
            $this->setup();
            $this->onSetup();
            $this->addSubmitButtonIfSet();
            $this->didSetup = true;
        }

        return $this;
    }

    public function handleRequest(Request $request = null)
    {
        if ($request !== null) {
            $this->request = $request;
        }

        $this->prepareElements();

        if ($this->hasBeenSent()) {
            $post = $this->getRequest()->getPost();
            if ($this->hasBeenSubmitted()) {
                $this->beforeValidation($post);
                if ($this->isValid($post)) {
                    $this->onSuccess();
                } else {
                    $this->onFailure();
                }
            } else {
                $this->setDefaults($post);
            }
        } else {
            // Well...
        }

        return $this;
    }

    public function translate($string)
    {
        if ($this->icingaModuleName === null) {
            return t($string);
        } else {
            return mt($this->icingaModuleName, $string);
        }
    }

    public function onSuccess()
    {
        $this->redirectOnSuccess();
    }

    public function setSuccessMessage($message)
    {
        $this->successMessage = $message;
        return $this;
    }

    public function getSuccessMessage($message = null)
    {
        if ($message !== null) {
            return $message;
        }
        if ($this->successMessage === null) {
            return t('Form has successfully been sent');
        }
        return $this->successMessage;
    }

    public function redirectOnSuccess($message = null)
    {
        $url = $this->successUrl ?: $this->getAction();
        $this->notifySuccess($this->getSuccessMessage($message));
        $this->redirectAndExit($url);
    }

    public function onFailure()
    {
    }

    public function notifySuccess($message = null)
    {
        if ($message === null) {
            $message = t('Form has successfully been sent');
        }
        Notification::success($message);
        return $this;
    }

    public function notifyError($message)
    {
        Notification::error($message);
        return $this;
    }

    protected function redirectAndExit($url)
    {
        Icinga::app()->getFrontController()->getResponse()->redirectAndExit($url);
    }

    public function getRequest()
    {
        if ($this->request === null) {
            $this->request = Icinga::app()->getFrontController()->getRequest();
        }
        return $this->request;
    }

    public function hasBeenSent()
    {
        if ($this->hasBeenSent === null) {
            $req = $this->getRequest();
            if ($req->isPost()) {
                $post = $req->getPost();
                $this->hasBeenSent = array_key_exists(self::ID, $post) &&
                    $post[self::ID] === $this->getName();
            } else {
                $this->hasBeenSent === false;
            }
        }

        return $this->hasBeenSent;
    }

    protected function detectName()
    {
        if ($this->formName !== null) {
            $this->setName($this->formName);
        } else {
            $this->setName(get_class($this));
        }
    }
}
