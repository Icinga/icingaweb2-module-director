<?php

namespace dipl\Html\FormDecorator;

use dipl\Html\BaseHtmlElement;
use dipl\Html\FormElement\BaseFormElement;
use dipl\Html\Html;
use dipl\Html\HtmlDocument;

class DdDtDecorator extends BaseHtmlElement
{
    protected $tag = 'dl';

    protected $dt;

    protected $dd;

    /** @var HtmlDocument */
    protected $wrapped;

    protected $ready = false;

    /**
     * @param HtmlDocument $document
     * @return static
     */
    public function wrap(HtmlDocument $document)
    {
        // TODO: ignore hidden

        $newWrapper = clone($this);
        $newWrapper->wrapped = $document;
        $document->addWrapper($newWrapper);

        return $newWrapper;
    }

    protected function renderLabel()
    {
        if ($this->wrapped instanceof BaseFormElement) {
            $label = $this->wrapped->getLabel();
            if (strlen($label)) {
                return Html::tag('label', null, $label);
            }
        }

        return null;
    }

    public function XXrenderAttributes()
    {
        // TODO: only when sent?!
        if ($this->wrapped instanceof BaseFormElement) {
            if (! $this->wrapped->isValid()) {
                $this->getAttributes()->add('class', 'errors');
            }
        }

        return parent::renderAttributes();
    }

    protected function renderDescription()
    {
        if ($this->wrapped instanceof BaseFormElement) {
            $description = $this->wrapped->getDescription();
            if (strlen($description)) {
                return Html::tag('p', ['class' => 'description'], $description);
            }
        }

        return null;
    }

    protected function renderErrors()
    {
        if ($this->wrapped instanceof BaseFormElement) {
            $errors = [];
            foreach ($this->wrapped->getMessages() as $message) {
                $errors[] = Html::tag('p', ['class' => 'error'], $message);
            }

            if (! empty($errors)) {
                return $errors;
            }
        }

        return null;
    }

    public function add($content)
    {
        if ($content !== $this->wrapped) {
            parent::add($content);
        }

        return $this;
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     */
    protected function assemble()
    {
        $this->add([$this->dt(), $this->dd()]);
        $this->ready = true;
    }

    public function dt()
    {
        if ($this->dt === null) {
            $this->dt = Html::tag('dt', null, $this->renderLabel());
        }

        return $this->dt;
    }

    /**
     * @return \dipl\Html\HtmlElement
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function dd()
    {
        if ($this->dd === null) {
            $this->dd = Html::tag('dd', null, [
                $this->wrapped,
                $this->renderErrors(),
                $this->renderDescription()
            ]);
        }

        return $this->dd;
    }
}
