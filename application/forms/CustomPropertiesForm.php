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
        $varCount = 0;
        foreach ($this->objectProperties as $objectProperty) {
            $inheritedVar = [];
            if (isset($inheritedVars[$objectProperty['key_name']])) {
                $inheritedVar = [$inheritedVars[$objectProperty['key_name']], $origins->{$objectProperty['key_name']}];
            }

            $elementName = "var_$varCount";
            $this->preparePropertyElement($elementName, $objectProperty, inheritedValue: $inheritedVar);
            $varCount += 1;
        }

        $this->addElement(
            $submitButton
        );
    }

    protected function preparePropertyElement(
        string $elementName,
        array $objectProperty,
        FieldsetElement $parentElement = null,
        $inheritedValue = [],
    ): void {
        if ($parentElement) {
            $elementName = $parentElement->getName() . '_' . $elementName;
        }

        $isInstantiable = $objectProperty['instantiable'] === 'y';
        $fieldType = $this->fetchFieldType($objectProperty['value_type'], $isInstantiable);
        $placeholder = '';
        if ($inheritedValue && ! (is_array($inheritedValue[0]) || $objectProperty['value_type'] === 'bool')) {
            $placeholder = $inheritedValue[0]
                . sprintf($this->translate(' (inherited from "%s")'), $inheritedValue[1]);
        }

        $fieldName = $objectProperty['key_name'];
        if ($fieldType === 'boolean') {
            $field = new IplBoolean($fieldName, ['label' => $objectProperty['label'],'decorator' => ['ViewHelper']]);
        } elseif ($fieldType === 'extensibleSet') {
            $placeholder = ! empty($inheritedValue[0])
                ? implode(', ', $inheritedValue[0])
                . sprintf($this->translate(' (inherited from "%s")'), $inheritedValue[1])
                : '';

            $field = (new ArrayElement($elementName, [
                'label'   => $objectProperty['label']
            ]))
                ->setPlaceholder($placeholder)
                ->setVerticalTermDirection();
        } elseif ($fieldType === 'collection') {
            $field = new FieldsetElement($elementName, [
                'label'   => $objectProperty['label'],
                'class'   => ['dictionary-element']
            ]);

//            $this->addElement('hidden', $elementName . '_name', ['value' => $fieldName]);
        } else {
            $field = $this->createElement(
                $fieldType,
                $elementName,
                [
                    'label'   => $objectProperty['label'],
                    'placeholder' => $placeholder
                ],
            );
        }


        if ($parentElement) {
            $parentElement->addElement($field);
            $parentElement->addElement('hidden', $elementName . '_name', ['value' => $fieldName]);
        } else {
            $this->addElement($field);
            $this->addElement('hidden', $elementName . '_name', ['value' => $fieldName]);
        }

        if ($field instanceof FieldsetElement) {
            $propertyItems = $this->fetchPropertyItems(Uuid::fromBytes($objectProperty['uuid']));

            if (! $isInstantiable) {
                foreach ($propertyItems as $itemKey => $propertyItem) {
                    $propertyInherited = [];
                    if (isset($inheritedValue[0][$propertyItem['key_name']])) {
                        $propertyInherited = [$inheritedValue[0][$propertyItem['key_name']], $inheritedValue[1]];
                    }

                    $this->preparePropertyElement(
                        $propertyItems[$itemKey]['var_name'],
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
                        $elementName . '_' . 'var_' . $prefix,
                        ['class' => ['dictionary-item', 'dictionary-element']]
                    );
                    $field->addElement($propertyField);

                    $propertyItemLabel = $this->createElement(
                        'text',
                        "label",
                        [
                            'label' => $this->translate('Item Label'),
                            'required' => true,
                            'class' => 'autosubmit'
                        ],
                    );

                    $propertyField->addElement($propertyItemLabel);
                    $field->registerElement($propertyField);
                    $propertyField->setLabel($propertyItemLabel->getValue());

                    $subVarCount = 0;
                    foreach ($propertyItems as $propertyItem) {
                        $inheritedVar = [];
                        if (! empty($inheritedValue) && isset($inheritedValue[0][$propertyItemLabel->getValue()])) {
                            $inheritedVar = [$inheritedValue[0][$propertyItemLabel->getValue()], $inheritedValue[1]];
                        }

                        $this->preparePropertyElement(
                            "var_$subVarCount",
                            $propertyItem,
                            $propertyField,
                            $inheritedVar
                        );

                        $subVarCount += 1;
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

                    $propertyField = new FieldsetElement($elementName . '_var_' . $tempNumberItem, [
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
                    $subVarCount = 0;
                    foreach ($propertyItems as $propertyItem) {
                        $this->preparePropertyElement("var_$subVarCount", $propertyItem, $propertyField);
                        $subVarCount += 1;
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
                        $propertyField->populate($field->getPopulatedValue('var_' . $numberItem) ?? []);
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

    public function fetchPropertyItems(UuidInterface $parentUuid): ?array
    {
        $db = $this->object->getConnection()->getDbAdapter();
        $query = $db->select()
            ->from('director_property')
            ->where('parent_uuid = ?', $parentUuid->getBytes());

        $propertyItems = $db->fetchAll($query, fetchMode: PDO::FETCH_ASSOC);

        foreach ($propertyItems as $idx => $propertyItem) {
            $propertyItems[$propertyItem['key_name']] = $propertyItem;
            $propertyItems[$propertyItem['key_name']]['var_name'] = "var_$idx";
            unset($propertyItems[$idx]);
        }

        return $propertyItems;
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
            $name = $element->getName();
            // TODO: This is a hack and will be removed once the alternative solution is implemented
            if (! $element->isIgnored() && preg_match('/^var(_\d+)?$/', $name) === 1) {
                $value = $element->getValue();
                $fieldName = $this->getElement($name . '_name')->getValue();
                if ($element instanceof FieldsetElement) {
                    foreach ($value as $key => $item) {
                        if (preg_match('/^var(_\d+)_var(_\d+)?$/', $key) === 1 && isset($value[$key . '_name'])) {
                            $value[$value[$key . '_name']]  = $item;
                            unset($value[$key]);
                            unset($value[$key . '_name']);
                        } elseif (
                            in_array($key, ['initial-count', 'added-count', 'prefixes', 'inherited-value'], true)
                        ) {
                            unset($value[$key]);
                        } elseif (substr($key, 0, strlen('item')) === 'item') {
                            $idx = (int) substr($key, -1);
                            $value[$idx] = $value[$key];
                            unset($value[$key]);
                        } elseif (preg_match('/^var(_\d+)_var(_\d+)?$/', $key) === 1 && isset($value[$key]['label'])) {
                            $label = $value[$key]['label'] ?? '';
                            unset($value[$key]['label']);
                            foreach ($value[$key] as $k => $v) {
                                if (
                                    ! $element->isIgnored()
                                    && preg_match('/^var(_\d+)_var(_\d+)_var(_\d+)?$/', $k) === 1
                                ) {
                                    if (! is_array($v) || array_keys($v) === range(0, count($v) - 1)) {
                                        $value[$label][$value[$key][$k . '_name']] = $v;
                                    } else {
                                        foreach ($v as $kk => $vv) {
                                            if (
                                                ! $element->isIgnored()
                                                && preg_match('/^var(_\d+)_var(_\d+)_var(_\d+)_var(_\d+)?$/', $kk) === 1
                                            ) {
                                                $v[$v[$kk . '_name']]  = $vv;
                                            }

                                            unset($v[$kk]);
                                            unset($v[$kk . '_name']);
                                        }

                                        $value[$label][$value[$key][$k . '_name']] = $v;
                                    }


                                    unset($value[$key][$k]);
                                    unset($value[$key][$k . '_name']);
                                }
                            }
                        }
                    }
                }

                $values[$fieldName] = $value;
            }
        }

        return $values;
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
