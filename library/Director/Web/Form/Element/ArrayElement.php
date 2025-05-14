<?php

namespace Icinga\Module\Director\Web\Form\Element;

use ipl\Html\Attribute;
use ipl\Web\FormElement\TermInput;

class ArrayElement extends TermInput
{
    /** @var string  */
    private $placeHolder = '';

    protected $defaultAttributes = ['class' => 'array-input'];

    public function setPlaceHolder(string $placeHolder): static
    {
        $this->placeHolder = $placeHolder;

        return $this;
    }

    protected function assemble()
    {
        parent::assemble();

        $valuePlaceHolder = $this->translate('Separate multiple values by comma.');
        if ($this->placeHolder) {
            $valuePlaceHolder = $this->placeHolder . '. ' . $valuePlaceHolder;
        }

        $this->getElement('value')->getAttributes()
            ->setAttribute(Attribute::create('placeholder', $valuePlaceHolder));
    }
}