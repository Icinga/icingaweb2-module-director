<?php

namespace Icinga\Module\Director\Web\Form;

use Icinga\Application\Icinga;
use Icinga\Exception\ProgrammingError;
use Icinga\Web\Notification;
use Icinga\Web\Request;
use Icinga\Web\Response;
use Icinga\Web\Url;
use Exception;

/**
 * QuickForm wants to be a base class for simple forms
 */
abstract class QuickForm extends QuickBaseForm
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

    protected $submitButtonName;

    protected $deleteButtonName;

    protected $fakeSubmitButtonName;

    /**
     * Whether form elements have already been created
     */
    protected $didSetup = false;

    protected $isApiRequest = false;

    public function __construct($options = null)
    {
        parent::__construct($options);

        $this->setMethod('post');
        $this->getActionFromRequest()
            ->createIdElement()
            ->regenerateCsrfToken()
            ->setPreferredDecorators();
    }

    protected function getActionFromRequest()
    {
        $this->setAction(Url::fromRequest());
        return $this;
    }

    protected function setPreferredDecorators()
    {
        $this->setAttrib('class', 'autofocus');
        $this->setDecorators(
            array(
                'Description',
                array('FormErrors', array('onlyCustomFormErrors' => true)),
                'FormElements',
                'Form'
            )
        );

        return $this;
    }

    protected function addSubmitButtonIfSet()
    {
        if (false === ($label = $this->getSubmitLabel())) {
            return;
        }

        if ($this->submitButtonName && $el = $this->getElement($this->submitButtonName)) {
            return;
        }

        $el = $this->createElement('submit', $label)
            ->setLabel($label)
            ->setDecorators(array('ViewHelper'));
        $this->submitButtonName = $el->getName();
        $this->addElement($el);

        $fakeEl = $this->createElement('submit', '_FAKE_SUBMIT', array(
            'role' => 'none',
            'tabindex' => '-1',
        ))
            ->setLabel($label)
            ->setDecorators(array('ViewHelper'));
        $this->fakeSubmitButtonName = $fakeEl->getName();
        $this->addElement($fakeEl);

        $this->addDisplayGroup(
            array($this->fakeSubmitButtonName),
            'fake_button',
            array(
                'decorators' => array('FormElements'),
                'order' => 1,
            )
        );

        $grp = array(
            $this->submitButtonName,
            $this->deleteButtonName
        );
        $this->addDisplayGroup($grp, 'buttons', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'DtDdWrapper',
            ),
            'order' => 1000,
        ));
    }

    protected function addSimpleDisplayGroup($elements, $name, $options)
    {
        if (! array_key_exists('decorators', $options)) {
            $options['decorators'] = array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            );
        }

        return $this->addDisplayGroup($elements, $name, $options);
    }

    protected function createIdElement()
    {
        if ($this->isApiRequest()) {
            return $this;
        }
        $this->detectName();
        $this->addHidden(self::ID, $this->getName());
        $this->getElement(self::ID)->setIgnore(true);
        return $this;
    }

    public function getSentValue($name, $default = null)
    {
        $request = $this->getRequest();
        if ($request->isPost() && $this->hasBeenSent()) {
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

    public function setApiRequest($isApiRequest = true)
    {
        $this->isApiRequest = $isApiRequest;
        return $this;
    }

    public function isApiRequest()
    {
        if ($this->isApiRequest === null) {
            if ($this->request === null) {
                throw new ProgrammingError(
                    'Early acess to isApiRequest(). This is not possible, sorry'
                );
            }

            return $this->getRequest()->isApiRequest();
        } else {
            return $this->isApiRequest;
        }
    }

    public function regenerateCsrfToken()
    {
        if ($this->isApiRequest()) {
            return $this;
        }
        if (! $element = $this->getElement(self::CSRF)) {
            $this->addHidden(self::CSRF, CsrfToken::generate());
            $element = $this->getElement(self::CSRF);
        }
        $element->setIgnore(true);

        return $this;
    }

    public function removeCsrfToken()
    {
        $this->removeElement(self::CSRF);
        return $this;
    }

    public function setSuccessUrl($url, $params = null)
    {
        if (! $url instanceof Url) {
            $url = Url::fromPath($url);
        }
        if ($params !== null) {
            $url->setParams($params);
        }
        $this->successUrl = $url;
        return $this;
    }

    public function getSuccessUrl()
    {
        $url = $this->successUrl ?: $this->getAction();
        if (! $url instanceof Url) {
            $url = Url::fromPath($url);
        }

        return $url;
    }

    protected function beforeSetup()
    {
    }

    public function setup()
    {
    }

    protected function onSetup()
    {
    }

    public function setAction($action)
    {
        if ($action instanceof Url) {
            $action = $action->getAbsoluteUrl('&');
        }

        return parent::setAction($action);
    }

    public function hasBeenSubmitted()
    {
        if ($this->hasBeenSubmitted === null) {
            $req = $this->getRequest();
            if ($req->isApiRequest()) {
                return $this->hasBeenSubmitted = true;
            }
            if ($req->isPost()) {
                if (! $this->hasSubmitButton()) {
                    return $this->hasBeenSubmitted = $this->hasBeenSent();
                }

                $this->hasBeenSubmitted = $this->pressedButton(
                    $this->fakeSubmitButtonName,
                    $this->getSubmitLabel()
                ) || $this->pressedButton(
                    $this->submitButtonName,
                    $this->getSubmitLabel()
                );
            } else {
                $this->hasBeenSubmitted = false;
            }
        }

        return $this->hasBeenSubmitted;
    }

    protected function hasSubmitButton()
    {
        return $this->submitButtonName !== null;
    }

    protected function pressedButton($name, $label)
    {
        $req = $this->getRequest();
        if (! $req->isPost()) {
            return false;
        }

        $req = $this->getRequest();
        $post = $req->getPost();

        return array_key_exists($name, $post)
            && $post[$name] === $label;
    }

    protected function beforeValidation($data = array())
    {
    }

    public function prepareElements()
    {
        if (! $this->didSetup) {
            $this->beforeSetup();
            $this->setup();
            $this->onSetup();
            $this->didSetup = true;
        }

        return $this;
    }

    public function handleRequest(Request $request = null)
    {
        if ($request === null) {
            $request = $this->getRequest();
        } else {
            $this->setRequest($request);
        }

        $this->prepareElements();
        $this->addSubmitButtonIfSet();

        if ($this->hasBeenSent()) {
            $post = $request->getPost();
            if ($this->hasBeenSubmitted()) {
                $this->beforeValidation($post);
                if ($this->isValid($post)) {
                    try {
                        $this->onSuccess();
                    } catch (Exception $e) {
                        $this->addException($e);
                        $this->onFailure();
                    }
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

    public function addException(Exception $e, $elementName = null)
    {
        $file = preg_split('/[\/\\\]/', $e->getFile(), -1, PREG_SPLIT_NO_EMPTY);
        $file = array_pop($file);
        $msg = sprintf(
            '%s (%s:%d)',
            $e->getMessage(),
            $file,
            $e->getLine()
        );

        if ($el = $this->getElement($elementName)) {
            $el->addError($msg);
        } else {
            $this->addError($msg);
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
        if ($this->isApiRequest()) {
            // TODO: Set the status line message?
            $this->successMessage = $this->getSuccessMessage($message);
            return;
        }

        $url = $this->getSuccessUrl();
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
        /** @var Response $response */
        $response = Icinga::app()->getFrontController()->getResponse();
        $response->redirectAndExit($url);
    }

    protected function setHttpResponseCode($code)
    {
        Icinga::app()->getFrontController()->getResponse()->setHttpResponseCode($code);
        return $this;
    }

    protected function onRequest()
    {
    }

    public function setRequest(Request $request)
    {
        if ($this->request !== null) {
            throw new ProgrammingError('Unable to set request twice');
        }

        $this->request = $request;
        $this->prepareElements();
        $this->onRequest();
        return $this;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        if ($this->request === null) {
            /** @var Request $request */
            $request = Icinga::app()->getFrontController()->getRequest();
            $this->setRequest($request);
        }
        return $this->request;
    }

    public function hasBeenSent()
    {
        if ($this->hasBeenSent === null) {

            /** @var Request $req */
            if ($this->request === null) {
                $req = Icinga::app()->getFrontController()->getRequest();
            } else {
                $req = $this->request;
            }

            if ($req->isApiRequest()) {
                $this->hasBeenSent = true;
            } elseif ($req->isPost()) {
                $post = $req->getPost();
                $this->hasBeenSent = array_key_exists(self::ID, $post) &&
                    $post[self::ID] === $this->getName();
            } else {
                $this->hasBeenSent = false;
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
