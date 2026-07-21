<?php

namespace Icinga\Module\Director\Forms\DictionaryElements;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Forms\CustomVariablesForm;
use Icinga\Module\Director\Forms\Validator\DatalistEntryValidator;
use Icinga\Module\Director\Web\Form\Element\ArrayElement;
use Icinga\Module\Director\Web\Form\Element\IplBoolean;
use Icinga\Module\Director\Web\Form\Element\SensitiveElement;
use ipl\Html\Attributes;
use ipl\Html\Contract\FormElement;
use ipl\Html\FormElement\FieldsetElement;
use ipl\Html\HtmlElement;
use ipl\Validator\CallbackValidator;
use ipl\Web\FormElement\TermInput;
use ipl\Web\Url;
use PDO;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Zend_Db_Adapter_Abstract;

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

    /** @var ?FormElement Remove button */
    private ?FormElement $removeButton = null;

    public function __construct(string $name, array $items, $attributes = null)
    {
        $this->fields = $items;

        parent::__construct($name, $attributes);
    }

    private static function getDb(): Zend_Db_Adapter_Abstract
    {
        return Db::fromResourceName(Config::module('director')->get('db', 'resource'))->getDbAdapter();
    }

    private static function fetchItemType(UuidInterface $uuid): ?string
    {
        $db = static::getDb();
        $query = $db->select()
            ->from(
                ['dp' => 'director_property'],
                ['value_type' => 'dp.value_type']
            )
            ->where('dp.parent_uuid = ?', Db\DbUtil::quoteBinaryCompat($uuid->getBytes(), $db));

        $itemType = $db->fetchOne($query);

        return $itemType === false ? null : $itemType;
    }

    /**
     * Fetch datalist entries for a given property uuid.
     *
     * @param UuidInterface $uuid
     *
     * @return array
     */
    private static function fetchDataListEntries(UuidInterface $uuid): array
    {
        $db = static::getDb();
        $query = $db->select()
            ->from(
                ['dle' => 'director_datalist_entry'],
                ['entry_name' => 'dle.entry_name', 'entry_value' => 'dle.entry_value']
            )
            ->join(['dl' => 'director_datalist'], 'dl.id = dle.list_id', [])
            ->join(['dpl' => 'director_property_datalist'], 'dl.uuid = dpl.list_uuid', [])
            ->where('dpl.property_uuid = ?', Db\DbUtil::quoteBinaryCompat($uuid->getBytes(), $db));

        return  $db->fetchPairs($query);
    }

    protected function assemble(): void
    {
        if (empty($this->fields)) {
            return;
        }

        $this->addElement('hidden', 'name', ['value' => $this->fields['key_name'] ?? '']);
        $this->addElement('hidden', 'type', ['value' => $this->fields['value_type'] ?? '']);
        $this->addElement('hidden', 'label', ['value' => $this->fields['label'] ?? '']);
        $this->addElement('hidden', 'parent_type', ['value' => $this->fields['parent_type'] ?? '']);

        $this->addElement('hidden', 'inherited');
        $this->addElement('hidden', 'inherited_from');

        $valElementName = 'var';
        $type = $this->getElement('type')->getValue();
        $label = $this->getElement('label')->getValue();

        if ($this->removeButton !== null) {
            $this->addAttributes(['class' => ['removable']]);
            $this->addElement('checkbox', 'item_required', [
                'label' => $this->translate('Required'),
                'class' => 'item-required-checkbox',
                'value' => ($this->fields['required_current'] ?? false) ? 'y' : 'n'
            ]);
            $this->addHtml(new HtmlElement(
                'div',
                null,
                $this->removeButton
            ));
        }

        if ($label === null) {
            $label = $this->getElement('name')->getValue();
        }

        $uuid = Uuid::fromBytes($this->fields['uuid']);
        // Pass down this item's own stored value, so each child gets its current value
        // too. Without this, a nested sensitive field has nothing to fall back on when
        // it comes back blank.
        $children = static::fetchChildrenItems(
            $uuid,
            $this->fields['value_type'] ?? '',
            ['value' => $this->fields['value'] ?? []]
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
                [
                    'label' => $label . ' (Number)',
                    'placeholder' => $placeholder,
                    'step' => 'any'
                ]
            );
        } elseif ($type == 'bool') {
            $this->addElement(
                new IplBoolean(
                    $valElementName,
                    ['label' => $label, 'placeholder' => $placeholder]
                )
            );
        } elseif ($type === 'sensitive') {
            $this->addElement(
                new SensitiveElement(
                    $valElementName,
                    [
                        'label' => $label . ' (Sensitive)',
                        'autocomplete' => 'off'
                    ]
                )
            );
        } elseif ($type === 'dynamic-array') {
            $this->addElement((new ArrayElement($valElementName))
                ->shouldAutoSubmit()
                ->setVerticalTermDirection()
                ->setPlaceHolder($placeholder)
                ->setLabel($label . ' (Array)'));
        } elseif (str_starts_with($type, 'datalist-')) {
            $isStrict = substr($type, strlen('datalist-')) === 'strict';
            $itemType = self::fetchItemType($uuid);
            $datalistEntries = self::fetchDataListEntries($uuid);
            if ($itemType === 'string') {
                if ($isStrict) {
                    $this->addElement(
                        'select',
                        $valElementName,
                        [
                            'label' => $label . ' (Datalist String [strict])',
                            'placeholder' => $placeholder,
                            'value' => '',
                            'options' => ['' => $this->translate('- Please choose -')]
                                + $datalistEntries
                        ]
                    );
                } else {
                    $fieldsetName = $this->getName();
                    $listEntriesInput = $this->createElement('text', $valElementName, [
                        'autocomplete' => 'off',
                        'ignore' => true,
                        'label' => $label . ' (Datalist String [non-strict])',
                        'data-enrichment-type' => 'completion',
                        'data-auto-submit' => true,
                        'data-term-suggestions' => "#{$valElementName}-suggestions-{$fieldsetName}",
                        'data-suggest-url' => Url::fromPath('director/suggestions/datalist-entry', [
                            'uuid' => Uuid::fromBytes($this->fields['uuid'])->toString(),
                            'showCompact' => true,
                            '_disableLayout' => true
                        ])
                    ]);

                    $fieldset = new HtmlElement('fieldset');
                    $this->registerElement($listEntriesInput);
                    $searchInput = $this->createElement('hidden', "{$valElementName}-search", ['ignore' => true]);
                    $this->registerElement($searchInput);
                    $fieldset->addHtml($searchInput);
                    $labelInput = $this->createElement('hidden', "{$valElementName}-label", ['ignore' => true]);
                    $this->registerElement($labelInput);
                    $fieldset->addHtml($labelInput);

                    $this->decorate($listEntriesInput);

                    $fieldset->addHtml(
                        $listEntriesInput,
                        new HtmlElement('div', Attributes::create([
                            'id' => "{$valElementName}-suggestions-{$fieldsetName}",
                            'class' => 'search-suggestions'
                        ]))
                    );

                    $this->addHtml($fieldset);
                }
            } elseif ($itemType === 'dynamic-array') {
                $listEntriesInput = (new ArrayElement($valElementName))
                    ->shouldAutoSubmit()
                    ->setSuggestedValues($datalistEntries)
                    ->setVerticalTermDirection()
                    ->setSuggestionUrl(Url::fromPath('director/suggestions/datalist-entry', [
                        'uuid' => Uuid::fromBytes($this->fields['uuid'])->toString(),
                        'showCompact' => true,
                        '_disableLayout' => true
                    ]));

                if ($isStrict) {
                    $termValidator = function (array $terms) use ($datalistEntries) {
                        (new DatalistEntryValidator())
                            ->setDatalistEntries($datalistEntries)
                            ->isValid($terms);
                    };

                    $listEntriesInput
                        ->setLabel($label . ' (Datalist Array [strict])')
                        ->on(TermInput::ON_ENRICH, $termValidator)
                        ->on(TermInput::ON_ADD, $termValidator)
                        ->on(TermInput::ON_PASTE, $termValidator)
                        ->on(TermInput::ON_SAVE, $termValidator);
                } else {
                    $listEntriesInput->setLabel($label . ' (Datalist Array [non-strict])');
                }

                $this->addElement($listEntriesInput);
            }
        } elseif ($type === 'fixed-dictionary' || $type === 'fixed-array') {
            $this->addElement(
                (new Dictionary($valElementName, $children))
                    ->setLabel($label . ' (' . ucfirst(substr($type, strlen('fixed-'))) . ')')
            );
        } elseif ($type === 'dynamic-dictionary') {
            $this->addElement((new NestedDictionary(
                $valElementName,
                $children,
                ['inherited_from' => $inheritedFrom, 'value' => $inherited],
                $this->fields['value'] ?? []
            ))->setLabel($label . ' (Dictionary)')->setUuid(Uuid::fromBytes($this->fields['uuid'])));
        } else {
            $this->addElement(
                'text',
                $valElementName,
                [
                    'label' => $label . ' (' . ucfirst($type) . ')',
                    'placeholder' => $placeholder
                ]
            );
        }

        if ($this->fields['required'] ?? false) {
            $valueElement = $this->getElement($valElementName);

            // fixed-array/fixed-dictionary push 'inherited' onto their children
            // instead of carrying it themselves, so check there.
            $isInherited = ($type === 'fixed-array' || $type === 'fixed-dictionary')
                ? ($valueElement instanceof Dictionary && $valueElement->hasInheritedValue())
                : ! empty($inherited);

            if (! $isInherited) {
                $this->markValueRequired($valueElement);
            }
        }
    }

    /**
     * Mark the given value element required
     *
     * A Dictionary/NestedDictionary always has a value thanks to its own hidden
     * bookkeeping fields, so a plain setRequired() would never trigger for one.
     * Its real content is checked separately, through a validator.
     *
     * @param FormElement $element
     *
     * @return void
     */
    private function markValueRequired(FormElement $element): void
    {
        $element->setRequired(true);

        if (! ($element instanceof Dictionary || $element instanceof NestedDictionary)) {
            return;
        }

        $element->addValidators([
            new CallbackValidator(function ($value, CallbackValidator $validator) use ($element) {
                // Ignore the untouched-child type defaults (0, 'n', ...), or a
                // blank fixed-array would read as filled in.
                $realValue = CustomVariablesForm::filterEmpty($element->getDictionary(false));
                if (! CustomVariablesForm::isValueUnset($realValue)) {
                    return true;
                }

                $validator->addMessage($this->translate('This field is required.'));

                return false;
            })
        ]);
    }

    /**
     * Whether this item's own value is inherited from an ancestor
     *
     * @return bool
     */
    public function hasInheritedValue(): bool
    {
        return ! empty($this->ensureAssembled()->getElement('inherited')->getValue());
    }

    public function populate($values)
    {
        if (empty($values)) {
            return parent::populate($values);
        }

        if (
            $values['type'] === 'datalist-non-strict'
            && self::fetchItemType(Uuid::fromBytes($this->fields['uuid'])) === 'string'
        ) {
            $datalistEntries = array_flip(self::fetchDataListEntries(Uuid::fromBytes($this->fields['uuid'])));
            $varValue = is_string($values['var'] ?? null) ? $values['var'] : '';
            $values['var'] = $varValue;

            if (isset($datalistEntries[$varValue])) {
                $values['var-search'] = $datalistEntries[$varValue];
                $values['var-label'] = $varValue;
            } else {
                $values['var-search'] = $varValue;
            }
        }

        return parent::populate($values);
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

        $property['uuid'] = DbUtil::binaryResult($property['uuid'] ?? '');

        if (
            $property['value_type'] === 'dynamic-array'
            || (
                in_array($property['value_type'], ['datalist-strict', 'datalist-non-strict'], true)
                && self::fetchItemType(Uuid::fromBytes($property['uuid'])) === 'dynamic-array'
            )
        ) {
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
        } elseif (
            $property['value_type'] === 'datalist-non-strict'
            && self::fetchItemType(Uuid::fromBytes($property['uuid'])) === 'string'
        ) {
            $dataListEntries = self::fetchDataListEntries(Uuid::fromBytes($property['uuid']));
            $value = is_string($property['value'] ?? null) ? $property['value'] : '';
            if (isset($dataListEntries[$value])) {
                $values['var'] = $dataListEntries[$value];
                $values['var-search'] = $value;
                $values['var-label'] = $dataListEntries[$value];
            } else {
                $values['var'] = $value;
                $values['var-search'] = $value;
            }
        } elseif ($property['value_type'] === 'sensitive') {
            // Send the DUMMYPASSWORD placeholder, not the real secret. The field itself
            // can't tell a stored secret apart from a value the user just typed, so we
            // mask it here, before it reaches the field.
            $values['var'] = ($property['value'] ?? '') !== '' ? SensitiveElement::DUMMYPASSWORD : '';
            // Never write the inherited secret itself into the 'inherited' hidden field's
            // DOM value; only its presence is needed downstream (fixed-array default-value
            // logic in getItem()), not its content.
            $values['inherited'] = ($property['inherited'] ?? '') !== '' ? '1' : '';
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
    private static function fetchChildrenItems(
        UuidInterface $parentUuid,
        string $parentType,
        array $values = []
    ): array {
        $db = static::getDb();

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
            ->where('dp.parent_uuid = ?', Db\DbUtil::quoteBinaryCompat($parentUuid->getBytes(), $db))
            ->joinLeft(
                ['cdp' => 'director_property'],
                'cdp.parent_uuid = dp.uuid',
                []
            )
            ->group(['dp.uuid', 'dp.key_name', 'dp.value_type', 'dp.label'])
            ->order('children')
            ->order('key_name');

        $propertyItems = $db->fetchAll($query, fetchMode: PDO::FETCH_ASSOC);

        if ($parentType === 'fixed-array') {
            // For a fixed array, key_name is the item's position, not a label, so order by
            // it numerically instead of the lexicographic "children"/key_name order above
            usort($propertyItems, fn($a, $b) => (int) $a['key_name'] <=> (int) $b['key_name']);
        }

        foreach ($propertyItems as $key => $propertyItem) {
            $propertyItem['uuid'] = DbUtil::binaryResult($propertyItem['uuid']);
            $propertyItem['parent_uuid'] = DbUtil::binaryResult($propertyItem['parent_uuid']);
            $propertyItems[$key] = $propertyItem;
        }

        if (empty($values)) {
            return $propertyItems;
        }

        return self::mergeChildValues($propertyItems, $parentType, $values);
    }

    /**
     * Add values to a set of child item definitions that were already fetched
     *
     * Used by fetchChildrenItems() for a single parent, and by NestedDictionary, which
     * reuses the same child definitions for every entry of a dynamic-dictionary and
     * merges in each entry's own value.
     *
     * @param array $propertyItems Children item definitions, keyed by their position
     * @param string $parentType
     * @param array $values
     *
     * @return array Children item definitions keyed by key_name, each carrying its own value
     */
    public static function mergeChildValues(array $propertyItems, string $parentType, array $values): array
    {
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
     * Set the remove button.
     *
     * @param ?FormElement $removeButton
     *
     * @return $this
     */
    public function setRemoveButton(?FormElement $removeButton): static
    {
        $this->removeButton = $removeButton;

        return $this;
    }

    /**
     * Whether this item's own value is unchanged from what it started with
     *
     * Lets a parent fixed-array/fixed-dictionary tell an untouched child from an
     * edited one. Resubmitting an already stored value must never count as a touch.
     *
     * @return bool
     */
    public function isUnchanged(): bool
    {
        $itemValue = $this->getElement('var');

        if ($itemValue instanceof Dictionary) {
            return $itemValue->allChildrenUnchanged();
        }

        if ($itemValue instanceof SensitiveElement && $itemValue->wasSubmittedUnchanged()) {
            return true;
        }

        $submitted = $itemValue->getValue();
        if ($submitted === '') {
            $submitted = null;
        }

        $stored = $this->fields['value'] ?? null;
        if ($stored === '') {
            $stored = null;
        }

        return $submitted === $stored;
    }

    /**
     * Get the dictionary item value
     *
     * @param bool $applyUnchangedDefaults Apply the untouched-child type default (0, 'n', ...).
     *                                     Storage needs it, a required check does not.
     *
     * @return DictionaryItemDataType
     */
    public function getItem(bool $applyUnchangedDefaults = true): array
    {
        $values = ['name' => $this->getElement('name')->getValue()];
        $itemValue = $this->getElement('var');
        if ($itemValue instanceof NestedDictionary or $itemValue instanceof Dictionary) {
            // Fixed-array/fixed-dictionary is all or nothing, one edited child rewrites
            // the whole thing. A top level property with nothing touched has nothing
            // to save, so leave it alone. A nested one must still report its value
            // since its own parent needs it to build its value.
            $isTopLevelProperty = empty($this->getElement('parent_type')->getValue());
            $isUntouched = $isTopLevelProperty
                && $itemValue instanceof Dictionary
                && $itemValue->allChildrenUnchanged();

            if (! $isUntouched) {
                $values['value'] = $itemValue->getDictionary($applyUnchangedDefaults);

                if ($this->getElement('type')->getValue() === 'fixed-array') {
                    $value = $values['value'];
                    ksort($value);
                    $values['value'] = array_values($value);
                }
            }
        } elseif (
            $this->getElement('type')->getValue() === 'datalist-non-strict'
            && self::fetchItemType(Uuid::fromBytes($this->fields['uuid'])) === 'string'
        ) {
            $values['value'] = $this->getElement('var-search')->getValue();
        } else {
            $type = $this->getElement('type')->getValue();
            $parentType = $this->getElement('parent_type')->getValue();

            if (empty($parentType) && ! empty($this->getElement('inherited')->getValue())) {
                $values['value'] = $itemValue->getValue();
            } else {
                $defaultValue = null;

                // Only fall back to the type default if this item was never touched.
                // A field cleared on purpose must stay cleared, not bounce back to it.
                if (
                    $applyUnchangedDefaults
                    && ($parentType === 'fixed-array' || $parentType === 'fixed-dictionary')
                    && $this->isUnchanged()
                ) {
                    match ($type) {
                        'string', 'sensitive' => $defaultValue = '',
                        'number' => $defaultValue = 0,
                        'bool' => $defaultValue = 'n',
                        'fixed-array', 'dynamic-array' => $defaultValue = [],
                        'datalist-strict', 'datalist-non-strict' => $defaultValue =
                            self::fetchItemType(Uuid::fromBytes($this->fields['uuid'])) === 'string' ? '' : [],
                        default => $defaultValue = null
                    };
                }

                $values['value'] = $itemValue->getValue() ?? $defaultValue;
            }

            // If a sensitive field still has the DUMMYPASSWORD placeholder, keep the old
            // secret. A stored value of "0" is still a real, previously-set secret, not
            // an absent one - only null/'' (never actually set, or explicitly cleared)
            // skip the restore.
            if (
                $type === 'sensitive'
                && $itemValue instanceof SensitiveElement
                && $itemValue->wasSubmittedUnchanged()
                && $this->fields['value'] !== null
                && $this->fields['value'] !== ''
            ) {
                $values['value'] = $this->fields['value'];
            }
        }

        $markForRemovalElement = 'delete-' . $this->getName();
        if ($this->hasElement($markForRemovalElement)) {
            $values['delete'] = $this->getElement($markForRemovalElement)->getValue();
        }

        if ($this->hasElement('item_required')) {
            $values['required'] = $this->getElement('item_required')->getValue() === 'y';
        }

        return $values;
    }
}
