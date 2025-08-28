<?php

namespace Icinga\Module\Director\Forms\DictionaryElements;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Web\Form\Element\ArrayElement;
use Icinga\Module\Director\Web\Form\Element\IplBoolean;
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

    public function __construct(string $name, array $items, $attributes = null)
    {
        $this->fields = $items;

        parent::__construct($name, $attributes);
    }

    protected function assemble(): void
    {
        $this->addElement('hidden', 'name', ['value' => $this->fields['key_name'] ?? '']);
        $this->addElement('hidden', 'type', ['value' => $this->fields['value_type'] ?? '']);
        $this->addElement('hidden', 'inherited');
        $this->addElement('hidden', 'inherited_from');

        $valElementName = 'var';
        $type = $this->getElement('type')->getValue();
        $label = $this->getElement('name')->getValue();

        $children = static::fetchChildrenItems(Uuid::fromBytes($this->fields['uuid']));
        $inherited = $this->getElement('inherited')->getValue();
        $inheritedFrom = $this->getElement('inherited_from')->getValue();

        $placeholder = '';
        if ($inherited) {
            $placeholder = $inherited . ' (' . sprintf($this->translate('Inherited from %s'), $inheritedFrom) . ')';
        }

        if ($type == 'string' || $type === 'number') {
            $this->addElement('text', $valElementName, ['label' => $label, 'placeholder' => $placeholder]);
        } elseif ($type === 'array') {
            $this->addElement('hidden', 'instantiable', ['value' => $this->fields['instantiable'] ?? 'n']);
            $isInstantiable = $this->getElement('instantiable')->getValue();
            if ($isInstantiable === 'y') {
                $this->addElement((new ArrayElement($valElementName))
                    ->setVerticalTermDirection()
                    ->setPlaceHolder($placeholder)
                    ->setLabel($label));
            } else {
                $this->addElement((new Dictionary($valElementName, $children))->setLabel($label));
            }
        } elseif ($type == 'bool') {
            $this->addElement(new IplBoolean($valElementName, ['label' => $label, 'placeholder' => $placeholder]));
        } elseif ($type === 'dict') {
            $this->addElement('hidden', 'instantiable', ['value' => $this->fields['instantiable'] ?? 'n']);
            $isInstantiable = $this->getElement('instantiable')->getValue();
            if ($isInstantiable === 'y') {
                $this->addElement((new NestedDictionary(
                    $valElementName,
                    $children,
                    ['inherited_from' => $inheritedFrom, 'value' => $inherited]
                ))->setLabel($label));
            } else {
                $this->addElement((new Dictionary($valElementName, $children))->setLabel($label));
            }
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
        $keyName = $property['key_name'] ?? '';
        if ($keyName !== '' && is_numeric($keyName)) {
            $keyName = $property['label'];
        }

        $values = [
            'name' => $keyName,
            'type' => $property['value_type'] ?? '',
        ];

        if ($property['value_type'] === 'string' || $property['value_type'] === 'number') {
            $values['var'] = $property['value'] ?? '';
            $values['inherited'] = $property['inherited'] ?? '';
            $values['inherited_from'] = $property['inherited_from'] ?? '';
        } elseif ($property['value_type'] === 'array') {
            if ($property['instantiable'] === 'y') {
                $values['instantiable'] = 'y';
                $values['var'] = $property['value'] ?? [];
                $values['inherited'] = implode(', ', $property['inherited'] ?? []);
                $values['inherited_from'] = $property['inherited_from'] ?? '';
            } else {
                $values['instantiable'] = 'n';
                $childrenValues = [
                    'value' => $property['value'] ?? [],
                    'inherited' => $property['inherited'] ?? [],
                    'inherited_from' => $property['inherited_from'] ?? ''
                ];

                $dictionaryItems = static::fetchChildrenItems(Uuid::fromBytes($property['uuid']), $childrenValues);
                $values['var'] = Dictionary::prepare($dictionaryItems);
            }
        } elseif ($property['value_type'] === 'dict') {
            $childrenValues = [
                'value' => $property['value'] ?? [],
                'inherited' => $property['inherited'] ?? [],
                'inherited_from' => $property['inherited_from'] ?? ''
            ];

            $dictionaryItems = static::fetchChildrenItems(Uuid::fromBytes($property['uuid']), $childrenValues);
            if ($property['instantiable'] !== 'y') {
                $values['instantiable'] = 'n';
                $values['var'] = Dictionary::prepare($dictionaryItems);
            } else {
                $values['instantiable'] = 'y';
                $values['var'] = NestedDictionary::prepare(
                    $dictionaryItems,
                    $property['value'] ?? []
                );

                $values['inherited'] = json_encode($property['inherited'] ?? '', JSON_PRETTY_PRINT);
                $values['inherited_from'] = $property['inherited_from'] ?? '';
            }
        }

        return $values;
    }

    /**
     * Fetch children items of the given parent item
     *
     * @param UuidInterface $parentUuid
     * @param array $values
     *
     * @return array
     */
    public static function fetchChildrenItems(UuidInterface $parentUuid, array $values = []): array
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
                            'instantiable' => 'dp.instantiable',
                            'children' => 'COUNT(cdp.uuid)'
                        ]
                    )
                    ->where('dp.parent_uuid = ?', $parentUuid->getBytes())
                    ->joinLeft(['cdp' => 'director_property'], 'cdp.parent_uuid = dp.uuid', [])
                    ->group(['dp.uuid', 'dp.key_name', 'dp.value_type', 'dp.label', 'dp.instantiable'])
                    ->order('children')
                    ->order('instantiable')
                    ->order('key_name');

        $propertyItems = $db->fetchAll($query, fetchMode: PDO::FETCH_ASSOC);
        if (empty($values)) {
            return $propertyItems;
        }


        $result = [];
        foreach ($propertyItems as $propertyItem) {
            if (isset($values['value'][$propertyItem['key_name']])) {
                $propertyItem['value'] = $values['value'][$propertyItem['key_name']];
            }

            if (isset($values['inherited'][$propertyItem['key_name']])) {
                $propertyItem['inherited'] = $values['inherited'][$propertyItem['key_name']];
                $propertyItem['inherited_from'] = $values['inherited_from'];
            }

            $result[] = $propertyItem;
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

            if ($this->getElement('type')->getValue() === 'array') {
                $values['value'] = array_values($values['value']);
            }
        } else {
            $values['value'] = $itemValue->getValue();
        }

        return $values;
    }
}
