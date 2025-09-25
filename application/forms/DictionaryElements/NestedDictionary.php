<?php

namespace Icinga\Module\Director\Forms\DictionaryElements;

use ipl\Html\FormElement\FieldsetElement;
use ipl\Html\HtmlElement;
use ipl\Web\Widget\EmptyStateBar;

/**
 * @phpstan-import-type DictionaryDataType from Dictionary
 */
class NestedDictionary extends FieldsetElement
{
    protected $defaultAttributes = ['class' => ['nested-dictionary', 'nested-fieldset']];

    /** @var array Nested dictionary items */
    protected $nestedItems = [];

    /** @var array{inherited_from: string, value: array} Inherited value */
    protected array $inheritedValue;

    public function __construct(
        string $name,
        array $nestedItems,
        array $inheritedValues,
        $attributes = null
    ) {
        $this->inheritedValue = $inheritedValues;
        $this->nestedItems = $nestedItems;

        parent::__construct($name, $attributes);
    }

    protected function assemble(): void
    {
        $expectedCount = (int) $this->getPopulatedValue('count', 0);
        $count = 0;
        $newCount = 0;

        if (! empty($this->inheritedValue['value'])) {
            $this->addElement(
                'textarea',
                'inherited_value',
                [
                    'label' => sprintf(
                        $this->translate('Inherited from "%s"'),
                        $this->inheritedValue['inherited_from']
                    ),
                    'value' => $this->inheritedValue['value'],
                    'readonly' => true,
                    'rows' => 10
                ]
            );
        }

        while ($count < $expectedCount) {
            $remove = $this->createElement(
                'submitButton',
                'remove_' . $count
            );

            $this->registerElement($remove);
            if ($remove->hasBeenPressed()) {
                $this->clearPopulatedValue($remove->getName());
                $this->clearPopulatedValue($count);

                // Re-index populated values to ensure proper association with form data
                foreach (range($count + 1, $expectedCount) as $i) {
                    $this->populate([$i - 1 => $this->getPopulatedValue($i) ?? []]);
                }
            } else {
                $newCount++;
            }

            $count++;
        }

        $addButton = $this->createElement('submitButton', 'add_property', [
            'label' => $this->translate('Add Property'),
            'class' => ['add-property'],
            'formnovalidate' => true
        ]);

        $this->registerElement($addButton);

        if ($addButton->hasBeenPressed()) {
            $remove = $this->createElement('submitButton', 'remove_' . $newCount, ['label' => 'Remove Item']);
            $this->registerElement($remove);
            $newCount++;
        }

        for ($i = 0; $i < $newCount; $i++) {
            $nestedDictionaryProperty = new NestedDictionaryItem($i, $this->nestedItems);
            $nestedDictionaryProperty->setRemoveButton($this->getElement('remove_' . $i));
            $this->addElement($nestedDictionaryProperty);
        }

        if ($newCount === 0) {
            $this->addHtml(new EmptyStateBar($this->translate('No items added')));
        }

        $this->addElement($addButton);

        $this->clearPopulatedValue('count');
        $this->addElement('hidden', 'count', ['ignore' => true, 'value' => $newCount]);
    }

    /**
     * Prepare nested dictionary for display
     *
     * @param array $nestedItems
     * @param array $values
     *
     * @return array
     */
    public static function prepare(array $nestedItems, array $values): array
    {
        $result = [];
        foreach ($values as $key => $nestedValue) {
            $nestedValue['key'] = $key;
            $result[] = NestedDictionaryItem::prepare(
                $nestedItems,
                $nestedValue
            );
        }

        return $result;
    }

    public function populate($values): static
    {
        if (! isset($values['count'])) {
            $values['count'] = count($values);
        }

        return parent::populate($values);
    }

    /**
     * Get the nested dictionary value
     *
     * @return array<int|string, DictionaryDataType>
     */
    public function getDictionary(): array
    {
        $values = [];
        $count = 0;
        foreach ($this->ensureAssembled()->getElements() as $element) {
            if ($element instanceof NestedDictionaryItem) {
                $property = $element->getItem();
                if (! empty($property['key']) && array_key_exists('value', $property)) {
                    $values[$property['key']] = $property['value'];
                } else {
                    $values[$count] = $property['value'];
                }

                $count++;
            }
        }

        return $values;
    }
}
