<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\Element\ArrayElement;
use Icinga\Web\Notification;
use Icinga\Web\Session;
use ipl\Html\FormElement\FieldsetElement;
use ipl\Html\FormElement\SubmitButtonElement;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use ipl\Web\FormElement\TermInput;
use ipl\Web\Widget\Icon;
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
        $inheritedVars = $this->object->getInheritedVars();
        $origins = $this->object->getOriginsVars();
        foreach ($this->objectProperties as $objectProperty) {
            $inheritedVar = [];
            if (isset($inheritedVars->{$objectProperty->key_name})) {
                $inheritedValue = $inheritedVars->{$objectProperty->key_name};
                if (is_object($inheritedVars->{$objectProperty->key_name})) {
                    $inheritedValue = (array) $inheritedValue;
                }

                $inheritedVar = [$inheritedValue, $origins->{$objectProperty->key_name}];
            }

            $this->preparePropertyElement($objectProperty, inheritedValue: $inheritedVar);
        }

        $this->addElement('submit', 'save', [
            'label' => $this->translate('Save'),
        ]);
    }

    protected function preparePropertyElement(
        object $objectProperty,
        FieldsetElement $parentElement = null,
        $inheritedValue = []
    ): void
    {
        $isInstantiable = $objectProperty->instantiable === 'y';
        $fieldType = $this->fetchFieldType($objectProperty->value_type, $isInstantiable);
        $placeholder = '';
        if ($inheritedValue && ! (is_array($inheritedValue[0]) || $objectProperty->value_type === 'bool')) {
            $placeholder = $inheritedValue[0]
                . sprintf($this->translate(' (inherited from "%s")'), $inheritedValue[1]);
        }

        if ($parentElement) {
            $fieldName = $objectProperty->key_name;

            $fieldName = is_numeric($fieldName)
                ? 'item-' . $fieldName
                : $fieldName;
        } else {
            $fieldName = $objectProperty->key_name;
        }

        if ($fieldType === 'boolean') {
            $options = ['' => sprintf(' - %s - ', $this->translate('Please choose')), 'y' => 'Yes', 'n' => 'No'];

            if (! empty($inheritedValue)) {
                $options[''] = ($inheritedValue[0] === 'y' ? 'Yes' : 'No')
                    . sprintf($this->translate(' (inherited from "%s")'), $inheritedValue[1]);
            }

            $field = $this->createElement(
                'select',
                $fieldName,
                [
                    'label'   => $objectProperty->label,
                    'options' => $options,
                    'value'   => ''
                ],
            );
        } elseif ($fieldType === 'extensibleSet') {
            $placeholder = ! empty($inheritedValue[0])
                ? implode(', ', $inheritedValue[0])
                . sprintf($this->translate(' (inherited from "%s")'), $inheritedValue[1])
                : '';
            $field = (new ArrayElement($fieldName, [
                'label'   => $objectProperty->label
            ]))->setPlaceholder($placeholder);
        } elseif ($fieldType === 'collection') {
            $field = new FieldsetElement($fieldName, [
                'label'   => $objectProperty->label,
            ]);
        } else {
            $field = $this->createElement(
                $fieldType,
                $fieldName,
                [
                    'label'   => $objectProperty->label,
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
            $propertyItems = $this->fetchPropertyItems(Uuid::fromBytes($objectProperty->uuid));

            if (! $isInstantiable) {
                foreach ($propertyItems as $key => $propertyItem) {
                    $propertyInherited = [];
                    if (isset($inheritedValue[0][$propertyItem->key_name])) {
                        $propertyInherited = [$inheritedValue[0][$propertyItem->key_name], $inheritedValue[1]];
                    }

                    $this->preparePropertyElement(
                        $propertyItem,
                        $field,
                        $propertyInherited
                    );
                }
            } elseif ($objectProperty->value_type === 'dict') {
                /** @var SubmitButtonElement $addItem */
                $addItem = $this->createElement(
                    'submitButton',
                    'add-item',
                    [
                        'label' => $this->translate('Add item'),
                        'formnovalidate' => true,
                    ],
                );

                $this->registerElement($addItem);

                $initialCount = $this->createElement(
                    'hidden',
                    'initial-count',
                    ['value' => 0]
                );

                $itemCount = $this->createElement(
                    'hidden',
                    'count',
                    ['value' => 0, 'class' => 'autosubmit'],
                );

                $field->addElement($initialCount);
                $field->addElement($itemCount);
                $field->registerElement($addItem);
                $this->registerElement($field);

                $numberItems = (int) $itemCount->getValue();

                $loadedItems = (int) $initialCount->getValue();

                $prefixElement = $this->createElement(
                    'hidden',
                    'prefixes'
                );

                $field->addElement($prefixElement);

                $prefixes = $prefixElement->getValue() !== null
                    ? explode(',', (string) $prefixElement->getValue())
                    : [];

                foreach ($prefixes as $idx => $prefix) {
                    $propertyField = new FieldsetElement('property-' . $prefix, ['class' => 'dictionary-item']);
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
                            'value'          => $idx,
                            'formnovalidate' => true
                        ]
                    );

                    $propertyField->registerElement($removeItem);
                    $propertyField->addHtml($removeItem);
                    if ($removeItem->hasBeenPressed()) {
                        $field->remove($propertyField);
                        unset($prefixes[$idx]);
                        $prefixElement->setValue(implode(',', $prefixes));
                    }
                }

                if ($addItem->hasBeenPressed()) {
                    $numberItems += 1;
                    $itemCount->setValue($numberItems);
                }

                $removedItems = 0;
                $removedItemIdx = null;
                for ($numberItem = $loadedItems; $numberItem < ($numberItems + $loadedItems); $numberItem++) {
                    $tempNumberItem = $numberItem;
                    if ($removedItems > 0 && $removedItemIdx < $numberItem) {
                        $tempNumberItem = $numberItem - 1;
                    }

                    $idx = $tempNumberItem - $loadedItems;

                    $propertyField = new FieldsetElement('property-' . $tempNumberItem, [
                        'label'   => $this->translate('New Property') . " $idx",
                        'class'   => ['dictionary-item']
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

                $numberItems -= $removedItems;
                $itemCount->setValue($numberItems);

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

        return $db->fetchAll($query);
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
        $type = $this->object->getShortTableName();

        $query = $this->db->getDbAdapter()
            ->select()
            ->from(
                ['dp' => 'director_property'],
                [
                    'instantiable' => 'dp.instantiable'
                ],
            )
            ->join(['iop' => "icinga_$type" . '_property'], 'dp.uuid = iop.property_uuid')
            ->where('iop.' . $type . '_uuid = ?', $this->object->uuid)
            ->where('dp.key_name = ?', $name);

        return $this->db->getDbAdapter()->fetchOne($query);
    }

    public function getValues()
    {
        $values = [];
        foreach ($this->getElements() as $element) {
            if (! $element->isIgnored()) {
                $value = $element->getValue();
                if ($element instanceof TermInput) {
                    $value = $value ? explode(',', $value) : [];
                } elseif ($element instanceof FieldsetElement) {
                    foreach ($value as $key => $item) {
                        if ($key === 'initial-count' || $key === 'count' || $key === 'prefixes') {
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

    public function hasBeenSubmitted()
    {
        $pressedButton = $this->getPressedSubmitElement();

        if ($pressedButton && $pressedButton->getName() === 'save') {
            return true;
        }

        return false;
    }

    protected function onSuccess()
    {
        $vars = $this->object->vars();

        $modified = false;
        foreach ($this->getValues() as $key => $value) {
            if (is_array($value)) {
                $value = array_filter($value);
            }

            $vars->set($key, $value);

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
