<?php

namespace dipl\Html;

use dipl\Html\FormElement\BaseFormElement;
use dipl\Html\FormElement\FormElementContainer;
use dipl\Html\FormElement\SubmitElement;
use dipl\Validator\MessageContainer;
use Icinga\Web\Request;
use InvalidArgumentException;

class Form extends BaseHtmlElement
{
    use FormElementContainer;
    use MessageContainer;

    protected $tag = 'form';

    protected $action;

    protected $method;

    /** @var SubmitElement */
    protected $submitButton;

    /** @var BaseHtmlElement|null */
    protected $defaultElementDecorator;

    private $populatedValues = [];

    /** @var Request TODO: nonono */
    private $request;

    private $isValid;

    /**
     * @param \Icinga\Web\Request $request
     * @return $this
     */
    public function setRequest($request)
    {
        $this->request = $request;

        if ($this->getAction() === null) {
            $this->setAction($request->getUrl()->getAbsoluteUrl('&'));
        }

        return $this;
    }

    /**
     * @return Request|null
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param Request $request
     * @return $this
     */
    public function handleRequest(Request $request)
    {
        $this->setRequest($request);
        if ($this->hasBeenSent()) {
            $this->populate($request->getParams());
        }

        $this->ensureAssembled();
        if ($this->hasBeenSubmitted()) {
            if ($this->isValid()) {
                try {
                    $this->onSuccess();
                } catch (\Exception $e) {
                    $this->addMessage($e->getMessage());
                    $this->onError();
                }
            } else {
                $this->onError();
            }
        } elseif ($this->hasBeenSent()) {
            $this->validatePartial();
        }

        return $this;
    }

    public function onSuccess()
    {
        // $this->redirectOnSuccess();
    }

    public function onError()
    {
        $error = Html::tag('p', ['class' => 'error'], 'ERROR: ');
        foreach ($this->getMessages() as $message) {
            $error->add($message);
        }

        $this->prepend($error);
    }

    // TODO: onElementRegistered
    public function onRegisteredElement($name, BaseFormElement $element)
    {
        if ($element instanceof SubmitElement && ! $this->hasSubmitButton()) {
            $this->setSubmitButton($element);
        }

        if (array_key_exists($name, $this->populatedValues)) {
            $element->setValue($this->populatedValues[$name]);
        }
    }

    public function isValid()
    {
        if ($this->isValid === null) {
            $this->validate();
        }

        return $this->isValid;
    }

    public function validate()
    {
        $valid = true;
        foreach ($this->elements as $element) {
            if ($element->isRequired() && ! $element->hasValue()) {
                $element->addMessage('This field is required');
                $valid = false;
                continue;
            }
            if (! $element->isValid()) {
                $valid = false;
            }
        }

        $this->isValid = $valid;
    }

    public function validatePartial()
    {
        foreach ($this->getElements() as $element) {
            if ($element->hasValue()) {
                $element->validate();
            }
        }
    }

    public function getValue($name, $default = null)
    {
        if ($this->hasElement($name)) {
            return $this->getElement($name)->getValue();
        } else {
            return $default;
        }
    }

    public function getValues()
    {
        $values = [];
        foreach ($this->getElements() as $element) {
            if (! $element->isIgnored()) {
                $values[$element->getName()] = $element->getValue();
            }
        }

        return $values;
    }

    /**
     * @return bool
     */
    public function hasBeenSent()
    {
        if ($this->request === null) {
            return false;
        }

        if ($this->request->getMethod() !== $this->getMethod()) {
            return false;
        }

        // TODO: Check form name element

        return true;
    }

    public function getSuccessUrl()
    {
        return $this->getAction();
    }

    public function redirectOnSuccess()
    {
        $this->request->getResponse()->redirectAndExit($this->getSuccessUrl());
    }

    /**
     * @return bool
     */
    public function hasBeenSubmitted()
    {
        if ($this->hasSubmitButton()) {
            return $this->getSubmitButton()->hasBeenPressed();
        } else {
            return $this->hasBeenSent();
        }
    }

    public function getSubmitButton()
    {
        return $this->submitButton;
    }

    public function hasSubmitButton()
    {
        return $this->submitButton !== null;
    }

    public function setSubmitButton(SubmitElement $element)
    {
        $this->submitButton = $element;

        return $this;
    }

    public function populate($values)
    {
        foreach ($values as $name => $value) {
            $this->populatedValues[$name] = $value;
            if ($this->hasElement($name)) {
                try {
                    $element = $this->getElement($name);
                } catch (InvalidArgumentException $exception) {
                    // This will not happen, as we checked for hasElement
                }

                $element->setValue($value);
            }
        }
    }

    /**
     * @return mixed
     */
    public function getMethod()
    {
        $method = $this->getAttributes()->get('method')->getValue();
        if ($method === null) {
            // WRONG. Problem:
            // right now we get the method in assemble, that's too late.
            // TODO: fix this via getMethodAttribute callback
            return 'POST';
        }

        return $method;
    }

    /**
     * @param $method
     * @return $this
     */
    public function setMethod($method)
    {
        $this->getAttributes()->set('method', strtoupper($method));

        return $this;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->getAttributes()->get('action')->getValue();
    }

    /**
     * @param $action
     * @return $this
     */
    public function setAction($action)
    {
        $this->getAttributes()->set('action', $action);

        return $this;
    }
}
