<?php

namespace Icinga\Module\Director\Forms\DictionaryElements;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Web\Form\Element\ArrayElement;
use Icinga\Module\Director\Web\Form\Element\IplBoolean;
use ipl\Html\FormElement\CheckboxElement;
use ipl\Html\FormElement\FieldsetElement;
use PDO;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @phpstan-type DictionaryItemDataType array{
 *      name: string,
 *      value: mixed
 *  }
 */
class DictionaryItem extends FieldsetElement
{
    protected $defaultAttributes = ['class' => ['no-border', 'dictionary-item']];

    /** @var array Dictionary Item Fields */
    private $fields;

    /** @var bool Whether to allow removal of item */
    private bool $isRemovable = false;

    public function __construct(string $name, array $items, $attributes = null)
    {
        $this->fields = $items;

        parent::__construct($name, $attributes);
    }

    public function setRemovable(bool $isRemovable = false): static
    {
        $this->isRemovable = $isRemovable;

        return $this;
    }

    protected function assemble(): void
    {
        $this->addElement('hidden', 'name', ['value' => $this->fields['key_name'] ?? '']);
        $this->addElement('hidden', 'type', ['value' => $this->fields['value_type'] ?? '']);
        $this->addElement('hidden', 'label', ['value' => $this->fields['key_name'] ?? '']);
        $this->addElement('hidden', 'parent_type', ['value' => $this->fields['parent_type'] ?? '']);

        $this->addElement('hidden', 'inherited');
        $this->addElement('hidden', 'inherited_from');

        $valElementName = 'var';
        $type = $this->getElement('type')->getValue();
        $label = $this->getElement('label')->getValue();

        if ($label === null) {
            $label = $this->getElement('name')->getValue();
        }

        $children = static::fetchChildrenItems(
            Uuid::fromBytes($this->fields['uuid']),
            $this->fields['value_type'] ?? ''
        );
        $inherited = $this->getElement('inherited')->getValue();
        $inheritedFrom = $this->getElement('inherited_from')->getValue();

        $placeholder = '';
        if ($inherited) {
            $placeholder = $inherited . ' (' . sprintf($this->translate('Inherited from %s'), $inheritedFrom) . ')';
        }

        if ($type === 'number') {
            $this->addElement(
                'number',
                $valElementName,
                ['label' => $label . ' (Number)', 'placeholder' => $placeholder, 'step' => 'any']
            );
        } elseif ($type == 'bool') {
            $this->addElement(new IplBoolean($valElementName, ['label' => $label, 'placeholder' => $placeholder]));
        } elseif ($type === 'dynamic-array') {
            $this->addElement((new ArrayElement($valElementName))
                ->setVerticalTermDirection()
                ->setPlaceHolder($placeholder)
                ->setLabel($label . ' (Array)'));
        } elseif ($type === 'fixed-dictionary' || $type === 'fixed-array') {
            $this->addElement(
                (new Dictionary($valElementName, $children))
                    ->setLabel($label . ' (' . ucfirst(substr($type, strlen('fixed-'))) . ')')
            );
        } elseif ($type === 'dynamic-dictionary') {
            $this->addElement((new NestedDictionary(
                $valElementName,
                $children,
                ['inherited_from' => $inheritedFrom, 'value' => $inherited]
            ))->setLabel($label . ' (Dictionary)'));
        } else {
            $this->addElement(
                'text',
                $valElementName,
                ['label' => $label . ' (' . ucfirst($type) . ')', 'placeholder' => $placeholder]
            );
        }

        if ($this->isRemovable && ! isset($this->fields['parent_uuid'])) {
            $markForRemoval = new CheckboxElement(
                'delete-' . $this->getName(),
                [
                    'label' => 'Mark for removal',
                    'description' => $this->translate(
                        'Removing the custom variable from this template,'
                        . ' will also remove it from the objects importing the template'
                    ),
                ]
            );

            $this->registerElement($markForRemoval);
            $this->addElement($markForRemoval);
        }
    }

    /**
     * Prepare the dictionary item for display
     *
     * @param array $property
     *
     * @return array
     */
    public static function prepare(array $property): array
    {
        $values = [
            'name' => $property['key_name'] ?? '',
            'label' => $property['label'] ?? '',
            'type' => $property['value_type'] ?? '',
            'parent_type' => $property['parent_type'] ?? ''
        ];

        if ($property['value_type'] === 'dynamic-array') {
            $values['var'] = $property['value'] ?? [];
            $values['inherited'] = implode(', ', $property['inherited'] ?? []);
            $values['inherited_from'] = $property['inherited_from'] ?? '';
        } elseif ($property['value_type'] === 'fixed-dictionary' || $property['value_type'] === 'fixed-array') {
            $childrenValues = ['value' => $property['value'] ?? []];

            if (! isset($property['value'])) {
                $childrenValues['inherited'] = $property['inherited'] ?? [];
                $childrenValues['inherited_from'] = $property['inherited_from'] ?? '';
            }

            $dictionaryItems = static::fetchChildrenItems(
                Uuid::fromBytes($property['uuid']),
                $property['value_type'],
                $childrenValues
            );
            $values['var'] = Dictionary::prepare($dictionaryItems);
        } elseif ($property['value_type'] === 'dynamic-dictionary') {
            $childrenValues = [
                'value' => $property['value'] ?? [],
                'inherited' => $property['inherited'] ?? [],
                'inherited_from' => $property['inherited_from'] ?? ''
            ];

            $dictionaryItems = static::fetchChildrenItems(
                Uuid::fromBytes($property['uuid']),
                $property['value_type'],
                $childrenValues
            );
            $values['var'] = NestedDictionary::prepare(
                $dictionaryItems,
                $property['value'] ?? []
            );

            $values['inherited'] = isset($property['inherited'])
                ? json_encode($property['inherited'], JSON_PRETTY_PRINT)
                : '';
            $values['inherited_from'] = $property['inherited_from'] ?? '';
        } else {
            $values['var'] = $property['value'] ?? '';
            $values['inherited'] = $property['inherited'] ?? '';
            $values['inherited_from'] = $property['inherited_from'] ?? '';
        }

        return $values;
    }

    /**
     * Fetch children items of the given parent item
     *
     * @param UuidInterface $parentUuid
     * @param string $parentType
     * @param array $values
     *
     * @return array
     */
    public static function fetchChildrenItems(UuidInterface $parentUuid, string $parentType, array $values = []): array
    {
        $db = Db::fromResourceName(Config::module('director')->get('db', 'resource'))->getDbAdapter();

        $query = $db->select()
                    ->from(
                        ['dp' => 'director_property'],
                        [
                            'key_name' => 'dp.key_name',
                            'uuid' => 'dp.uuid',
                            'value_type' => 'dp.value_type',
                            'label' => 'dp.label',
                            'parent_uuid' => 'dp.parent_uuid',
                            'children' => 'COUNT(cdp.uuid)'
                        ]
                    )
                    ->where('dp.parent_uuid = ?', $parentUuid->getBytes())
                    ->joinLeft(['cdp' => 'director_property'], 'cdp.parent_uuid = dp.uuid', [])
                    ->group(['dp.uuid', 'dp.key_name', 'dp.value_type', 'dp.label'])
                    ->order('children')
                    ->order('key_name');

        $propertyItems = $db->fetchAll($query, fetchMode: PDO::FETCH_ASSOC);
        if (empty($values)) {
            return $propertyItems;
        }

        $result = [];
        foreach ($propertyItems as $propertyItem) {
            $propertyItem['parent_type'] = $parentType;
            if (isset($values['value'][$propertyItem['key_name']])) {
                $propertyItem['value'] = $values['value'][$propertyItem['key_name']];
            }

            if (isset($values['inherited'][$propertyItem['key_name']])) {
                $propertyItem['inherited'] = $values['inherited'][$propertyItem['key_name']];
                $propertyItem['inherited_from'] = $values['inherited_from'];
            }

            $result[$propertyItem['key_name']] = $propertyItem;
        }

        return $result;
    }

    /**
     * Get the dictionary item value
     *
     * @return DictionaryItemDataType
     */
    public function getItem(): array
    {
        $values = ['name' => $this->getElement('name')->getValue()];
        $itemValue = $this->getElement('var');
        if ($itemValue instanceof NestedDictionary or $itemValue instanceof Dictionary) {
            $values['value'] = $itemValue->getDictionary();

            if ($this->getElement('type')->getValue() === 'fixed-array') {
                $value = $values['value'];
                ksort($value);
                $values['value'] = array_values($value);
            }
        } else {
            if (! empty($this->getElement('inherited')->getValue())) {
                $values['value'] = $itemValue->getValue();
            } else {
                $defaultValue = null;

                // Use the default value for fixed-array items only if the fixed array does not have an inherited value
                if ($this->getElement('parent_type')->getValue() === 'fixed-array') {
                    match ($this->getElement('type')->getValue()) {
                        'string' => $defaultValue = '',
                        'number' => $defaultValue = 0,
                        'bool' => $defaultValue = 'n',
                        'fixed-array', 'dynamic-array' => $defaultValue = []
                    };
                }

                $values['value'] = $itemValue->getValue() ?? $defaultValue;
            }
        }

        $markForRemovalElement = 'delete-' . $this->getName();
        if ($this->hasElement($markForRemovalElement)) {
            $values['delete'] = $this->getElement($markForRemovalElement)->getValue();
        }

        return $values;
    }
}
