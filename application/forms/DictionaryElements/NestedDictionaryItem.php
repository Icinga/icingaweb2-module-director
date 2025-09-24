<?php

namespace Icinga\Module\Director\Forms\DictionaryElements;

use ipl\Html\Contract\FormElement;
use ipl\Html\FormElement\FieldsetElement;
use ipl\Html\FormElement\SubmitButtonElement;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\Web\Widget\Icon;

/**
 * @phpstan-import-type DictionaryDataType from Dictionary
 * @phpstan-type NestedDictionaryItemDataType array{
 *     key: string,
 *     value: DictionaryDataType
 * }
 */
class NestedDictionaryItem extends FieldsetElement
{
    protected $defaultAttributes = ['class' => ['nested-dictionary-item', 'collapsible-item']];

    /** @var array Items in the nested dictionary property */
    protected array $items = [];

    /** @var ?SubmitButtonElement Remove button for the nested dictionary property*/
    private ?SubmitButtonElement $removeButton = null;

    public function __construct(string $name, array $items, $attributes = null)
    {
        $this->items = $items;

        parent::__construct($name, $attributes);
    }

    protected function assemble(): void
    {
        $id = str_replace(['[', ']'], '_', $this->getValueOfNameAttribute());
        $this->getAttributes()->set('id', $id);

        $this->addElement('text', 'key', [
            'label' => $this->translate('Key'),
            'required' => true
        ]);

        $this->addElement('hidden', 'state', [
            'value' => $this->getPopulatedValue('key') ? 'old' : 'new',
            'ignore' => true
        ]);

        if ($this->getElement('state')->getValue() === 'old') {
            $label = $this->getElement('key')->getValue();
        } else {
            $label = $this->translate('New Item');
        }

        $this->setLabel($label);
        if ($this->removeButton !== null) {
            $this->addHtml(new HtmlElement(
                'div',
                null,
                $this->removeButton->setLabel(new Icon('trash'))
                    ->setAttribute('formnovalidate', true)
                    ->setAttribute('class', ['remove-button'])
                    ->add(Text::create(' ' . $this->translate('Remove')))
            ));
        }

        $this->addElement(
            (new Dictionary('var', $this->items, ['class' => 'no-border']))
                ->setItems($this->items)
        );
    }

    /**
     * Set the remove button.
     *
     * @param ?FormElement $removeButton
     *
     * return $this
     */
    public function setRemoveButton(?FormElement $removeButton): static
    {
        $this->removeButton = $removeButton;

        return $this;
    }

    /**
     * Prepare the nested dictionary item value for display
     *
     * @param array $nestedItems
     * @param array $property
     *
     * @return array
     */
    public static function prepare(array $nestedItems, array $property): array
    {
        $nestedValues = [];
        foreach ($nestedItems as $nestedItem) {
            if (isset($property[$nestedItem['key_name']]) && ! empty($property[$nestedItem['key_name']])) {
                $nestedItem['value'] = $property[$nestedItem['key_name']];
            }

            $nestedValues[] = $nestedItem;
        }

        return [
            'key' => $property['key'],
            'var' => Dictionary::prepare($nestedValues)
        ];
    }

    /**
     * Get the nested dictionary item value
     *
     * @return NestedDictionaryItemDataType
     */
    public function getItem(): array
    {
        $this->ensureAssembled();
        $key = $this->getElement('key')->getValue();
        $values = [];
        $values['key'] = $key;
        $values['value'] = $this->getElement('var')->getDictionary();

        return $values;
    }
}
