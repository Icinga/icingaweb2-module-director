<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\Element\ArrayElement;
use Icinga\Module\Director\Web\Form\Element\IplBoolean;
use Icinga\Web\Notification;
use Icinga\Web\Session;
use ipl\Html\FormElement\FieldsetElement;
use ipl\Html\FormElement\SubmitButtonElement;
use ipl\Html\FormElement\SubmitElement;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use ipl\Web\FormElement\TermInput;
use ipl\Web\Widget\Icon;
use PDO;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class CustomPropertiesForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    public function __construct(
        public readonly DbConnection $db,
        public readonly IcingaObject $object,
        protected array $objectProperties = []
    ) {
        $this->addAttributes(['class' => ['custom-properties-form']]);
    }

    protected function assemble(): void
    {
        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));
        $inheritedVars = json_decode(json_encode($this->object->getInheritedVars()), JSON_OBJECT_AS_ARRAY);
        $origins = $this->object->getOriginsVars();

        /** @var SubmitElement $submitButton */
        $submitButton = $this->createElement('submit', 'save', [
            'label' => $this->translate('Save')
        ]);

        $this->registerElement($submitButton);

        $duplicateSubmit = $this->duplicateSubmitButton($submitButton);

        $this->addElement($duplicateSubmit);

        foreach ($this->objectProperties as $objectProperty) {
            $inheritedVar = [];
            if (isset($inheritedVars[$objectProperty['key_name']])) {
                $inheritedVar = [$inheritedVars[$objectProperty['key_name']], $origins->{$objectProperty['key_name']}];
            }

            $this->preparePropertyElement($objectProperty, inheritedValue: $inheritedVar);
        }

        $this->addElement(
            $submitButton
        );
    }

    protected function preparePropertyElement(
        array $objectProperty,
        FieldsetElement $parentElement = null,
        $inheritedValue = []
    ): void {
        $isInstantiable = $objectProperty['instantiable'] === 'y';
        $fieldType = $this->fetchFieldType($objectProperty['value_type'], $isInstantiable);
        $placeholder = '';
        if ($inheritedValue && ! (is_array($inheritedValue[0]) || $objectProperty['value_type'] === 'bool')) {
            $placeholder = $inheritedValue[0]
                . sprintf($this->translate(' (inherited from "%s")'), $inheritedValue[1]);
        }

        $fieldName = $objectProperty['key_name'];
        if ($parentElement) {
            $fieldName = is_numeric($fieldName)
                ? 'item-' . $fieldName
                : $fieldName;
        }

        if ($fieldType === 'boolean') {
//            $options = ['' => sprintf(' - %s - ', $this->translate('Please choose')), 'y' => 'Yes', 'n' => 'No'];

//            if (! empty($inheritedValue)) {
//                $options[''] = ($inheritedValue[0] === 'y' ? 'Yes' : 'No')
//                    . sprintf($this->translate(' (inherited from "%s")'), $inheritedValue[1]);
//            }

            $field = new IplBoolean($fieldName, ['label' => $objectProperty['label'],'decorator' => ['ViewHelper']]);
        } elseif ($fieldType === 'extensibleSet') {
            $placeholder = ! empty($inheritedValue[0])
                ? implode(', ', $inheritedValue[0])
                . sprintf($this->translate(' (inherited from "%s")'), $inheritedValue[1])
                : '';
            $field = (new ArrayElement($fieldName, [
                'label'   => $objectProperty['label']
            ]))
                ->setPlaceholder($placeholder)
                ->setVerticalTermDirection();
        } elseif ($fieldType === 'collection') {
            $field = new FieldsetElement($fieldName, [
                'label'   => $objectProperty['label'],
                'class'   => ['dictionary-element']
            ]);
        } else {
            $field = $this->createElement(
                $fieldType,
                $fieldName,
                [
                    'label'   => $objectProperty['label'],
                    'placeholder' => $placeholder
                ],
            );
        }

        if ($parentElement) {
            $parentElement->addElement($field);
        } else {
            $this->addElement($field);
        }

        if ($field instanceof FieldsetElement) {
            $propertyItems = $this->fetchPropertyItems(Uuid::fromBytes($objectProperty['uuid']));

            if (! $isInstantiable) {
                foreach ($propertyItems as $propertyItem) {
                    $propertyInherited = [];
                    if (isset($inheritedValue[0][$propertyItem['key_name']])) {
                        $propertyInherited = [$inheritedValue[0][$propertyItem['key_name']], $inheritedValue[1]];
                    }

                    $this->preparePropertyElement(
                        $propertyItem,
                        $field,
                        $propertyInherited
                    );
                }
            } elseif ($objectProperty['value_type'] === 'dict') {
                /** @var SubmitButtonElement $addItem */
                $addItem = $this->createElement(
                    'submitButton',
                    'add-item',
                    [
                        'label' => $this->translate('Add Item'),
                        'formnovalidate' => true
                    ],
                );

                $initialCountElement = $this->createElement(
                    'hidden',
                    'initial-count'
                );

                $addedCountElement = $this->createElement(
                    'hidden',
                    'added-count',
                    ['class' => 'autosubmit'],
                );

                $field->addElement($initialCountElement);
                $field->addElement($addedCountElement);
                $field->registerElement($addItem);
                $this->registerElement($field);

                $addedItemsCount = (int) $addedCountElement->getValue();
                $initialItemsCount = (int) $initialCountElement->getValue();

                $prefixElement = $this->createElement(
                    'hidden',
                    'prefixes'
                );

                $field->addElement($prefixElement);

                $prefixes = $prefixElement->getValue() !== null
                    ? explode(',', (string) $prefixElement->getValue())
                    : [];

                foreach ($prefixes as $idx => $prefix) {
                    $propertyField = new FieldsetElement(
                        'property-' . $prefix,
                        ['class' => ['dictionary-item', 'dictionary-element']]
                    );
                    $field->addElement($propertyField);

                    $propertyItemLabel = $this->createElement(
                        'text',
                        'label',
                        [
                            'label' => $this->translate('Item Label'),
                            'required' => true,
                            'class' => 'autosubmit'
                        ],
                    );

                    $propertyField->addElement($propertyItemLabel);
                    $field->registerElement($propertyField);
                    $propertyField->setLabel($propertyItemLabel->getValue());
                    foreach ($propertyItems as $propertyItem) {
                        $inheritedVar = [];
                        if (! empty($inheritedValue) && isset($inheritedValue[0][$propertyItemLabel->getValue()])) {
                            $inheritedVar = [$inheritedValue[0][$propertyItemLabel->getValue()], $inheritedValue[1]];
                        }

                        $this->preparePropertyElement(
                            $propertyItem,
                            $propertyField,
                            $inheritedVar
                        );
                    }

                    /** @var SubmitButtonElement $removeItem */
                    $removeItem = $this->createElement(
                        'submitButton',
                        "remove-item",
                        [
                            'class'          => 'remove-button',
                            'label'          => new Icon('minus', ['title' => 'Remove item']),
                            'value'          => $prefix,
                            'formnovalidate' => true
                        ]
                    );

                    $propertyField->registerElement($removeItem);
                    $propertyField->addHtml($removeItem);
                    if ($removeItem->hasBeenPressed()) {
                        $field->remove($propertyField);
                        unset($prefixes[$idx]);
                        $initialItemsCount -= 1;
                        $initialCountElement->setValue($initialItemsCount);
                        $prefixElement->setValue(implode(',', $prefixes));
                    }
                }

                if ($addItem->hasBeenPressed()) {
                    $addedItemsCount += 1;
                    $addedCountElement->setValue($addedItemsCount);
                }

                $removedItems = 0;
                $removedItemIdx = null;
                for (
                    $numberItem = $initialItemsCount;
                    $numberItem < ($addedItemsCount + $initialItemsCount);
                    $numberItem++
                ) {
                    $tempNumberItem = $numberItem;
                    if ($removedItems > 0 && $removedItemIdx < $numberItem) {
                        $tempNumberItem = $numberItem - 1;
                    }

                    $idx = $tempNumberItem - $initialItemsCount;

                    $propertyField = new FieldsetElement('property-' . $tempNumberItem, [
                        'label'   => $this->translate('New Property') . " $idx",
                        'class'   => ['dictionary-item', 'dictionary-element']
                    ]);

                    $field->addElement($propertyField);
                    $propertyItemLabel = $this->createElement(
                        'text',
                        'label',
                        [
                            'label' => $this->translate('Item Label'),
                            'required' => true
                        ],
                    );

                    $propertyField->addElement($propertyItemLabel);
                    foreach ($propertyItems as $propertyItem) {
                        $this->preparePropertyElement($propertyItem, $propertyField);
                    }

                    $removeItem = $this->createElement(
                        'submitButton',
                        "remove-item",
                        [
                            'class'          => ['remove-button', 'autosubmit'],
                            'label'          => new Icon('minus', ['title' => 'Remove item']),
                            'value'          => $tempNumberItem,
                            'formnovalidate' => true
                        ]
                    );

                    $propertyField->registerElement($removeItem);
                    $propertyField->addHtml($removeItem);
                    if (! $removedItems && $removeItem->hasBeenPressed()) {
                        $field->remove($propertyField);
                        $removedItemIdx = (int) $removeItem->getValue();
                        $removedItems += 1;
                    } else {
                        $propertyField->populate($field->getPopulatedValue('property-' . $numberItem) ?? []);
                    }
                }

                $addedItemsCount -= $removedItems;
                $addedCountElement->setValue($addedItemsCount);

                if ($addedItemsCount === 0 && $initialItemsCount === 0 && ! empty($inheritedValue)) {
                    $field->addElement(
                        'textarea',
                        'inherited-value',
                        [
                            'label' => sprintf($this->translate('Inherited from "%s"'), $inheritedValue[1]),
                            'value' => json_encode($inheritedValue[0], JSON_PRETTY_PRINT),
                            'readonly' => true,
                            'rows' => 10
                        ]
                    );
                }

                $field->addElement($addItem);
            }
        }
    }

    private function fetchPropertyItems(UuidInterface $parentUuid): ?array
    {
        $db = $this->db->getDbAdapter();
        $query = $db->select()
            ->from('director_property')
            ->where('parent_uuid = ?', $parentUuid->getBytes());

        return $db->fetchAll($query, fetchMode: PDO::FETCH_ASSOC);
    }

    protected function fetchFieldType(string $propertyType, bool $instantiable = false): string
    {
        // works only in PHP 8.0 and greater
        return match ($propertyType) {
            'bool' => 'boolean',
            'array' => $instantiable
                ? 'extensibleSet'
                : 'collection',
            'dict' => 'collection',
            default => 'text',
        };
    }

    private function isPropertyInstantiable(string $name): string
    {
        return isset($this->objectProperties[$name]) ? $this->objectProperties[$name]['instantiable'] : 'n';
    }

    public function getValues()
    {
        $values = [];
        foreach ($this->getElements() as $element) {
            if (! $element->isIgnored()) {
                $value = $element->getValue();
                if ($element instanceof FieldsetElement) {
                    foreach ($value as $key => $item) {
                        if (in_array($key, ['initial-count', 'added-count', 'prefixes', 'inherited-value'], true)) {
                            unset($value[$key]);
                        } elseif (substr($key, 0, strlen('item')) === 'item') {
                            $idx = (int) substr($key, -1);
                            $value[$idx] = $value[$key];
                            unset($value[$key]);
                        } elseif (substr($key, 0, strlen('property')) === 'property') {
                            $label = $value[$key]['label'] ?? '';
                            unset($value[$key]['label']);

                            $value[$label] = $value[$key];
                            unset($value[$key]);
                        }
                    }
                }

                $values[$element->getName()] = $value;
            }
        }

        return $values;
    }

    public function populate($values)
    {
        foreach ($values as $name => $value) {
            $newValues = [];
            if (is_array($value)) {
                if (
                    $this->isPropertyInstantiable($name) === 'y'
                    && array_keys($value) === range(0, count($value) - 1)
                ) {
                    $values[$name] = implode(',', $value);
                } elseif ($this->isPropertyInstantiable($name) === 'y') {
                    $nestedValues = [];
                    $i = 0;
                    $prefixes = [];
                    foreach ($value as $key => $item) {
                        if (! is_array($item)) {
                            break;
                        } else {
                            $nestedValues["property-$i"] = array_merge(
                                ['label' => $key],
                                $item,
                            );

                            $prefixes[] = $i;
                            $i += 1;
                        }
                    }

                    if (! empty($nestedValues)) {
                        $nestedValues['initial-count'] = $i;
                        $nestedValues['prefixes'] = implode(',', $prefixes);
                        unset($values[$name]);

                        $values[$name] = $nestedValues;
                    } elseif (array_keys($value) === range(0, count($value) - 1)) {
                        $newValue = [];
                        foreach (array_values($value) as $idx => $item) {
                            $newValue["item-$idx"] = $item;
                        }

                        $values[$name] = $newValue;
                    }
                } elseif (array_keys($value) === range(0, count($value) - 1)) {
                    $newValue = [];
                    foreach (array_values($value) as $idx => $item) {
                        $newValue["item-$idx"] = $item;
                    }

                    $values[$name] = $newValue;
                }
            }
        }

        return parent::populate($values);
    }

    private function filterEmpty(array $array): array
    {
        return array_filter(
            array_map(function ($item) {
                if (! is_array($item)) {
                    // Recursively clean nested arrays
                    return $item;
                }

                return $this->filterEmpty($item);
            }, $array),
            function ($item) {
                return is_bool($item) || ! empty($item);
            }
        );
    }

    protected function onSuccess()
    {
        $vars = $this->object->vars();

        $modified = false;
        var_dump($this->getValues());die;
        foreach ($this->getValues() as $key => $value) {
            if (is_array($value)) {
                $value = $this->filterEmpty($value);
            }

            if (! is_bool($value) && empty($value)) {
                $vars->set($key, null);
            } else {
                $vars->set($key, $value);
            }

            if ($modified === false && $vars->hasBeenModified()) {
                $modified = true;
            }
        }

        $vars->storeToDb($this->object);

        if ($modified) {
            Notification::success(
                sprintf(
                    $this->translate('Custom variables have been successfully modified for %s'),
                    $this->object->getObjectName(),
                ),
            );
        } else {
            Notification::success($this->translate('There is nothing to change.'));
        }
    }
}
