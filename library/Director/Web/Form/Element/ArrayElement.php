<?php

namespace Icinga\Module\Director\Web\Form\Element;

use ipl\Html\Attribute;
use ipl\Web\FormElement\TermInput;
use ipl\Web\FormElement\TermInput\RegisteredTerm;

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

        $this->getElement('value')
             ->getAttributes()
            ->registerAttributeCallback('placeholder', function () use ($valuePlaceHolder) {
                return $valuePlaceHolder;
            });
    }

    public function getValue($name = null, $default = null)
    {
        if ($name !== null) {
            return parent::getValue($name, $default);
        }

        $terms = [];
        foreach ($this->getTerms() as $term) {
            $terms[] = $term->render(',');
        }

        return $terms;
    }

    public function setValue($value)
    {
        if (is_array($value) && isset($value['value'])) {
            $separatedTerms = $value['value'] ?? '';
            parent::setValue($value);
        } elseif (is_array($value)) {
            $separatedTerms = implode(',', $value);
        } else {
            $separatedTerms = $value;
        }

        $terms = [];
        foreach ($this->parseValue((string) $separatedTerms) as $term) {
            $terms[] = new RegisteredTerm($term);
        }

        return $this->setTerms(...$terms);
    }
}
