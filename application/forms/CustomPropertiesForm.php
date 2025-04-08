<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Web\Notification;
use Icinga\Web\Session;
use ipl\Html\Attribute;
use ipl\Html\FormDecorator\DivDecorator;
use ipl\Html\FormElement\FieldsetElement;
use ipl\Html\FormElement\SubmitButtonElement;
use ipl\Html\HtmlElement;
use ipl\I18n\Translation;
use ipl\Tests\Html\TestDummy\SimpleFormElementDecorator;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use ipl\Web\FormDecorator\IcingaFormDecorator;
use ipl\Web\FormElement\TermInput;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class CustomPropertiesForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    public function __construct(
        public readonly DbConnection $db,
        public readonly IcingaObject $object,
    ) {
//        $this->addAttributes(['class' => ['director-form']]);
    }

    protected function assemble(): void
    {
        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));
        $objectProperties = $this->getObjectProperties();
        if ($objectProperties) {
            foreach ($objectProperties as $objectProperty) {
                $this->preparePropertyElement($objectProperty);
            }
        }

        $this->addElement('submit', 'save', [
            'label' => $this->translate('Save'),
        ]);
    }

    protected function preparePropertyElement(object $objectProperty, FieldsetElement $parentElement = null)
    {
        $isInstantiable = $objectProperty->instantiable === 'y';
        $fieldType = $this->fetchFieldType($objectProperty->value_type, $isInstantiable);

        if ($parentElement) {
            $fieldName = $objectProperty->key_name;

            $fieldName = is_numeric($fieldName)
                ? 'item-' . $fieldName
                : $fieldName;
        } else {
            $fieldName = $objectProperty->key_name;
        }

        if ($fieldType === 'boolean') {
            $field = $this->createElement(
                'select',
                $fieldName,
                [
                    'label'   => $objectProperty->label,
                    'value'   => 'n',
                    'options' => ['y' => 'Yes', 'n' => 'No'],
                ],
            );
        } elseif ($fieldType === 'extensibleSet') {
            $field = new TermInput($fieldName, [
                'label'   => $objectProperty->label,
                'placeholder' => $this->translate('Separate multiple values by comma'),
            ]);
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
                foreach ($propertyItems as $propertyItem) {
                    $this->preparePropertyElement($propertyItem, $field);
                }
            } elseif ($objectProperty->value_type === 'dict') {
                /** @var SubmitButtonElement $addItem */
                $addItem = $this->createElement(
                    'submit',
                    'add-item',
                    [
                        'label' => $this->translate('Add item'),
                    ],
                );

                $this->registerElement($addItem);

                $initialCount = $this->createElement(
                    'hidden',
                    'initial-count',
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
                for ($i = 0; $i < $loadedItems; $i++) {
                    $propertyField = new FieldsetElement('property-' . $i);
                    $field->addElement($propertyField);

                    $propertyItemLabel = $this->createElement(
                        'text',
                        'label',
                        [
                            'label' => $this->translate('Item Label'),
                            'class' => 'autosubmit',
                        ],
                    );

                    $propertyField->addElement($propertyItemLabel);
                    $field->registerElement($propertyField);
                    $propertyField->setLabel($propertyItemLabel->getValue());
                    foreach ($propertyItems as $propertyItem) {
                        $this->preparePropertyElement($propertyItem, $propertyField);
                    }
                }

                if ($addItem->hasBeenPressed()) {
                    $numberItems += 1;
                    $itemCount->setValue($numberItems);
                }

                for ($numberItem = $loadedItems; $numberItem < ($numberItems + $loadedItems); $numberItem++) {
                    $idx = $numberItem - $loadedItems;
                    $propertyField = new FieldsetElement('property-' . $numberItem, [
                        'label'   => $this->translate('New Property') . " $idx",
                    ]);

                    $field->addElement($propertyField);

                    $propertyItemLabel = $this->createElement(
                        'text',
                        'label',
                        [
                            'label' => $this->translate('Item Label'),
                        ],
                    );

                    $propertyField->addElement($propertyItemLabel);
                    foreach ($propertyItems as $propertyItem) {
                        $this->preparePropertyElement($propertyItem, $propertyField);
                    }
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

    protected function getObjectProperties(): ?array
    {
        if ($this->object->uuid === null) {
            return [];
        }

        $type = $this->object->getShortTableName();
        $query = $this->db->getDbAdapter()
            ->select()
            ->from(
                ['dp' => 'director_property'],
                [
                    'key_name' => 'dp.key_name',
                    'uuid' => 'dp.uuid',
                    'value_type' => 'dp.value_type',
                    'label' => 'dp.label',
                    'instantiable' => 'dp.instantiable',
                    'required' => 'iop.required',
                ],
            )
            ->join(['iop' => "icinga_$type" . '_property'], 'dp.uuid = iop.property_uuid')
            ->where('iop.' . $type . '_uuid = ?', $this->object->uuid);

        return $this->db->getDbAdapter()->fetchAll($query);
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


    public function isValid()
    {
        if ($this->getPressedSubmitElement()->getName() === 'delete') {
            $csrfElement = $this->getElement('CSRFToken');

            return $csrfElement->isValid();
        }

        return parent::isValid();
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
                        if ($key === 'initial-count' || $key === 'count') {
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
                } else {
                    $nestedValues = [];
                    $i = 0;
                    foreach ($value as $key => $item) {
                        if (! is_array($item)) {
                            break;
                        } else {
                            $nestedValues["property-$i"] = array_merge(
                                ['label' => $key],
                                $item,
                            );

                            $i += 1;
                        }
                    }

                    if (! empty($nestedValues)) {
                        $newValues[] = array_merge(
                            ['initial-count' => $i + 1],
                            $nestedValues,
                        );

                        $nestedValues['initial-count'] = $i;
                        unset($values[$name]);

                        $values[$name] = $nestedValues;
                    } elseif (array_keys($value) === range(0, count($value) - 1)) {
                        $newValue = [];
                        foreach (array_values($value) as $idx => $item) {
                            $newValue["item-$idx"] = $item;
                        }

                        $values[$name] = $newValue;
                    }
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
