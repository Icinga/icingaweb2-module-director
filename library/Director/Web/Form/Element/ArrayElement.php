<?php

namespace Icinga\Module\Director\Web\Form\Element;

use ipl\Web\FormElement\TermInput;
use ipl\Web\FormElement\TermInput\RegisteredTerm;

class ArrayElement extends TermInput
{
    /** @var string Placeholder text */
    private $placeholder = '';

    protected $defaultAttributes = ['class' => 'array-input'];

    /** @var array<string, string> Predefined values used for validation and term labels ['value' => 'label'] */
    private array $suggestedValues = [];

    public function setPlaceHolder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    protected function assemble()
    {
        parent::assemble();

        $valuePlaceHolder = $this->translate('Separate multiple values by comma.');
        if ($this->placeholder) {
            $valuePlaceHolder = $this->placeholder . '. ' . $valuePlaceHolder;
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
            $separatedTerms = $value['value'];
            parent::setValue($value);
        } elseif (is_array($value)) {
            $separatedTerms = implode(',', $value);
        } else {
            $separatedTerms = $value;
        }

        $terms = [];
        foreach ($this->parseValue((string) $separatedTerms) as $term) {
            $term = new RegisteredTerm($term);
            if (isset($this->suggestedValues[$term->getSearchValue()])) {
                $term->setLabel($this->suggestedValues[$term->getSearchValue()]);

            }

            $terms[] = $term;
        }

        return $this->setTerms(...$terms);
    }

    public function setSuggestedValues(array $suggestedValues): self
    {
        $this->suggestedValues = $suggestedValues;

        return $this;
    }
}
