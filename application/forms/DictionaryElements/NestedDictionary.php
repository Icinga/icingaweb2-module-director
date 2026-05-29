<?php

namespace Icinga\Module\Director\Forms\DictionaryElements;

use ipl\Html\FormElement\FieldsetElement;
use ipl\Html\Html;
use ipl\Web\Url;
use ipl\Web\Widget\EmptyStateBar;
use ipl\Web\Widget\Link;
use Ramsey\Uuid\UuidInterface;

class NestedDictionary extends FieldsetElement
{
    protected $defaultAttributes = ['class' => ['nested-dictionary', 'nested-fieldset']];

    public const UNDEFINED_KEY = '__undefined__';

    /** @var array Nested dictionary items */
    protected $nestedItems = [];

    /** @var UuidInterface Uuid of the dictionary */
    private UuidInterface $uuid;

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

    /**
     * Set the Uuid of the nested dictionary property
     *
     * @param UuidInterface $uuid
     *
     * @return $this
     */
    public function setUuid(UuidInterface $uuid)
    {
        $this->uuid = $uuid;

        return $this;
    }

    protected function assemble(): void
    {
        $expectedCount = (int) $this->getPopulatedValue('count', 0);
        $count = 0;
        $newCount = 0;

        if (! empty($this->inheritedValue['value'])) {
            $inheritedFrom = implode(
                ', ',
                array_map(
                    fn($item) => '"' . trim($item) . '"',
                    explode(',', $this->inheritedValue['inherited_from'])
                )
            );

            $this->addElement(
                'textarea',
                'inherited_value',
                [
                    'label' => sprintf(
                        $this->translate('Inherited from %s'),
                        $inheritedFrom
                    ),
                    'value' => $this->inheritedValue['value'],
                    'class' => 'inherited-value',
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
                $removedValue = $this->getPopulatedValue($count);
                $clearedId = null;
                if (isset($removedValue['id'])) {
                    $clearedId = $removedValue['id'];
                }

                $this->clearPopulatedValue($remove->getName());
                $this->clearPopulatedValue($count);

                // Re-index populated values to ensure proper association with form data
                foreach (range($count + 1, $expectedCount) as $i) {
                    $newPopulatedValue = $this->getPopulatedValue($count);
                    $newId = $newPopulatedValue['id'] ?? null;
                    $newPopulatedValue['id'] = $clearedId;
                    $this->populate([$i - 1 => $this->getPopulatedValue($i) ?? []]);
                    $clearedId = $newId;
                }
            } else {
                $newCount++;
            }

            $count++;
        }

        $addButton = $this->createElement('submitButton', 'add_item', [
            'label' => $this->translate('Add Item'),
            'class' => ['add-item'],
            'formnovalidate' => true
        ]);

        if (empty($this->nestedItems)) {
            $addButton->addAttributes([
                'disabled' => true,
                'title' => $this->translate('No fields have been configured for the dictionary'),
            ]);
        }

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
            if (empty($this->nestedItems)) {
                $this->addHtml(new EmptyStateBar(Html::sprintf(
                    $this->translate(
                        'No fields configured for this dictionary.'
                        . ' Add fields to the custom variable definition in %s.'
                    ),
                    new Link(
                        $this->translate('Custom Variables'),
                        Url::fromPath('director/customvar', ['uuid' => $this->uuid->toString()]),
                        ['data-base-target' => '_next']
                    )
                )));
            } else {
                $this->addHtml(new EmptyStateBar($this->translate('No items added')));
            }
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

    public function populate($values)
    {
        if (! isset($values['count'])) {
            $values['count'] = count($values);
        }

        return parent::populate($values);
    }

    /**
     * Get the nested dictionary value
     *
     * @return array<string, array>
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
                    $values[self::UNDEFINED_KEY . $count] = $property['value'];
                }

                $count++;
            }
        }

        return $values;
    }
}
