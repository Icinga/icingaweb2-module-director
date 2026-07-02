<?php

namespace Icinga\Module\Director\Web\Form\Element;

use ipl\Web\FormElement\TermInput;
use ipl\Web\FormElement\TermInput\RegisteredTerm;

class ArrayElement extends TermInput
{
    /** @var string Placeholder text */
    private $placeholder = '';

    /** @var bool Should the form be auto-submitted when a term is added or removed */
    private bool $shouldAutoSubmit = false;

    protected $defaultAttributes = ['class' => 'array-input'];

    /** @var array<string, string> Predefined values used for validation and term labels ['value' => 'label'] */
    private array $suggestedValues = [];

    /**
     * Sets the placeholder text for the current instance.
     *
     * @param string $placeholder The placeholder text to set.
     *
     * @return $this
     */
    public function setPlaceHolder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    protected function assemble(): void
    {
        parent::assemble();

        $valuePlaceHolder = $this->translate('Separate multiple values by comma.');
        if ($this->placeholder) {
            $valuePlaceHolder = $this->placeholder . '. ' . $valuePlaceHolder;
        }

        $this->getElement('value')
            ->getAttributes()
            ->set('data-no-auto-submit-on-remove', false)
            ->registerAttributeCallback('placeholder', function () use ($valuePlaceHolder) {
                return $valuePlaceHolder;
            })
            ->registerAttributeCallback('data-auto-submit', function () {
                return $this->shouldAutoSubmit;
            });
    }

    /**
     * Sets whether the form should auto-submit
     *
     * @param bool $shouldAutoSubmit Indicates if auto-submit should be enabled
     *
     * @return $this
     */
    public function shouldAutoSubmit(bool $shouldAutoSubmit = true): static
    {
        $this->shouldAutoSubmit = $shouldAutoSubmit;

        return $this;
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

    /**
     * Sets the suggested values for the instance.
     *
     * @param array $suggestedValues An array of suggested values to set.
     *
     * @return $this
     */
    public function setSuggestedValues(array $suggestedValues): static
    {
        $this->suggestedValues = $suggestedValues;

        return $this;
    }
}
