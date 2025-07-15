<?php

namespace Icinga\Module\Director\Web\Form;

use ipl\Html\Contract\FormElement;
use ipl\Html\Form;
use ipl\Html\FormElement\HiddenElement;
use ipl\Html\ValidHtml;

class PropertyTableSortForm extends Form
{
    protected $method = 'POST';

    /** @var string Name of the form */
    private $name;

    /** @var ValidHtml Property table to sort */
    private $table;

    public function __construct(string $name, ValidHtml $table)
    {
        $this->name = $name;
        $this->table = $table;
    }

    protected function assemble()
    {
        $this->addElement('hidden', '__FORM_NAME', ['value' => $this->name]);
        $this->addElement($this->createCsrfCounterMeasure());
        $this->addHtml($this->table);
    }

    /**
     * Create a form element to countermeasure CSRF attacks
     *
     * @return FormElement
     */
    protected function createCsrfCounterMeasure(): FormElement
    {
        $token = CsrfToken::generate();

        $options = [
            'ignore'        => true,
            'required'      => true,
            'validators'    => ['Callback' => function ($token) {
                return CsrfToken::isValid($token);
            }]
        ];

        $element = new class (QuickForm::CSRF, $options) extends HiddenElement {
            public function hasValue(): bool
            {
                return true; // The validator must run even if the value is empty
            }
        };

        $element->getAttributes()->registerAttributeCallback('value', function () use ($token) {
            return $token;
        });

        return $element;
    }
}
