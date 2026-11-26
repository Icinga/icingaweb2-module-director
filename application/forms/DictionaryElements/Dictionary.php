<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Forms\DictionaryElements;

use Icinga\Module\Director\Db\DbUtil;
use ipl\Html\Attributes;
use ipl\Html\FormElement\FieldsetElement;
use ipl\Html\Html;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\Web\Widget\EmptyStateBar;

class Dictionary extends FieldsetElement
{
    protected $defaultAttributes = ['class' => 'dictionary'];

    /** @var array Dictionary items */
    protected array $items = [];

    /** @var bool Whether to allow removal of item */
    protected bool $allowItemRemoval = false;

    /** @var bool Whether removable items offer the Required toggle */
    protected bool $allowRequiredToggle = true;

    /** @var bool Whether items offer an input for their value */
    protected bool $showValues = true;

    /** @var bool Whether the dictionary is an array */
    protected bool $isArray = false;

    /** @var bool Whether dictionary is a child dictionary */
    private bool $isChild = false;

    /** @var bool Whether items are shown in a collapsible section per category */
    private bool $groupByCategory = false;

    /** @var bool Whether items are shown as a single row with their label and input */
    private bool $flatItems = false;

    public function __construct(string $name, array $items, $attributes = null)
    {
        $this->items = $items;

        parent::__construct($name, $attributes);
    }

    /**
     * Set if the dictionary item could be removed
     *
     * @param bool $allow
     *
     * @return $this
     */
    public function setAllowItemRemoval(bool $allow = false): static
    {
        $this->allowItemRemoval = $allow;

        return $this;
    }

    /**
     * Set if removable items offer the Required toggle
     *
     * @param bool $allow
     *
     * @return $this
     */
    public function setAllowRequiredToggle(bool $allow = true): static
    {
        $this->allowRequiredToggle = $allow;

        return $this;
    }

    /**
     * Set if items offer an input for their value
     *
     * @param bool $show
     *
     * @return $this
     */
    public function setShowValues(bool $show = true): static
    {
        $this->showValues = $show;

        return $this;
    }

    /**
     * Set whether the dictionary is a child dictionary.
     *
     * @param bool $isChild
     *
     * @return $this
     */
    public function setIsChild(bool $isChild = true): static
    {
        $this->isChild = $isChild;

        return $this;
    }

    /**
     * Set whether items are shown in a collapsible section per category
     *
     * Items without a category go to a section called "Custom Variables". The items
     * have to be sorted with {@see sortByCategory()} already, a new section starts
     * whenever the category changes. Items added in this session are left out and
     * shown below all sections, until they have been saved.
     *
     * @param bool $groupByCategory
     *
     * @return $this
     */
    public function setGroupByCategory(bool $groupByCategory = true): static
    {
        $this->groupByCategory = $groupByCategory;

        return $this;
    }

    /**
     * Set whether items are shown as a single row with their label and input
     *
     * @see DictionaryItem::setFlat()
     *
     * @param bool $flatItems
     *
     * @return $this
     */
    public function setFlatItems(bool $flatItems = true): static
    {
        $this->flatItems = $flatItems;

        return $this;
    }

    protected function assemble(): void
    {
        $expectedCount = (int) $this->getPopulatedValue('item-count', 0);
        $count = 0;

        // Load previously removed items from the hidden field (no session). JSON
        // encoded, a plain ", " join would break on a key_name that itself contains ", ".
        $removedItemsPopulated = $this->getPopulatedValue('items_removed', '');
        $removedItems = $removedItemsPopulated !== ''
            ? array_fill_keys(json_decode($removedItemsPopulated, true) ?? [], true)
            : [];

        if ($this->allowItemRemoval) {
            while ($count < $expectedCount) {
                $remove = $this->createElement(
                    'submitButton',
                    'remove_' . $count,
                    [
                        'label' => 'Remove',
                        'class' => ['remove-property'],
                        'formnovalidate' => true
                    ]
                );

                $this->registerElement($remove);
                if ($remove->hasBeenPressed()) {
                    $removedValue = $this->getPopulatedValue($count);
                    if (isset($removedValue['name'])) {
                        $clearedItemName = $removedValue['name'];
                        if (isset($this->items[$clearedItemName])) {
                            $removedItems[$clearedItemName] = true;
                        }
                    }

                    $this->clearPopulatedValue('items_removed');
                    $this->clearPopulatedValue($remove->getName());
                    $this->clearPopulatedValue($count);
                    $this->populate(['items_removed' => json_encode(array_keys($removedItems))]);

                    // Re-index populated values to ensure proper association with form data
                    foreach (range($count + 1, $expectedCount) as $i) {
                        $this->populate([$i - 1 => $this->getPopulatedValue($i) ?? []]);
                    }
                }

                $count++;
            }
        }

        $addedItems = [];
        foreach ($this->items as $key => $item) {
            if (array_key_exists($key, $removedItems)) {
                unset($this->items[$key]);
            } elseif (isset($item['new'])) {
                $addedItems[] = $key;
            }
        }

        $this->addElement('hidden', 'items_removed', ['value' => json_encode(array_keys($removedItems))]);
        $this->addElement('hidden', 'items_added', ['value' => implode(', ', $addedItems)]);
        $count = 0;
        $section = null;
        $sectionName = null;
        foreach ($this->items as $item) {
            $item['uuid'] = DbUtil::binaryResult($item['uuid']);
            if (isset($item['parent_uuid'])) {
                $item['parent_uuid'] = DbUtil::binaryResult($item['parent_uuid']);
            }

            $element = (new DictionaryItem((string) $count, $item))
                ->setAllowRequiredToggle($this->allowRequiredToggle)
                ->setShowValue($this->showValues)
                ->setFlat($this->flatItems);

            // Only allow removal of items if the dictionary allows it and the item allows it
            if (
                $this->allowItemRemoval
                && $item['allow_removal']
                && $this->hasElement('remove_' . $count)
            ) {
                $element->setRemoveButton($this->getElement('remove_' . $count));
            }

            if ($this->groupByCategory && ! $this->isChild && ! isset($item['new'])) {
                $category = self::categoryOf($item);
                if ($section === null || $category !== $sectionName) {
                    $sectionName = $category;
                    $section = $this->createCategorySection($category);
                    $this->addHtml($section);
                }

                $this->registerElement($element);
                $this->decorate($element);
                $section->addHtml($element);
            } else {
                $this->addElement($element);
            }

            $count++;
        }

        $this->clearPopulatedValue('item-count');
        $itemCountInput = $this->createElement('hidden', 'item-count', ['ignore' => true, 'value' => $count]);
        $this->registerElement($itemCountInput);
        $this->addHtml(Html::tag('div', ['id' => $this->getName() . '-item-count'], $itemCountInput));


        $newVarSlot = Html::tag('div', ['id' => 'new-var-slot-' . $count]);

        if ($this->allowItemRemoval) {
            $this->addHtml($newVarSlot);
        }

        if ($count === 0) {
            if (! $this->isChild) {
                $message = $this->translate('No custom variables have been added yet');
            } else {
                $message = $this->translate('No fields configured');
            }

            if ($message !== '') {
                $this->addHtml(new EmptyStateBar($message));
            }
        }
    }

    /**
     * Sort items the way they are grouped by {@see setGroupByCategory()}
     *
     * By category name first, items without a category count as "Custom Variables".
     * Within a category by label, or by key name for an item without a label.
     *
     * @param array $items
     *
     * @return array The sorted items, as a list
     */
    public static function sortByCategory(array $items): array
    {
        $label = fn (array $item) => (string) ($item['label'] ?? '') !== ''
            ? (string) $item['label']
            : (string) $item['key_name'];

        usort($items, function (array $a, array $b) use ($label) {
            return strnatcasecmp(self::categoryOf($a), self::categoryOf($b))
                ?: strnatcasecmp($label($a), $label($b))
                ?: strcmp((string) $a['key_name'], (string) $b['key_name']);
        });

        return $items;
    }

    /**
     * Get the name of the section the given item is shown in
     *
     * @param array $item
     *
     * @return string
     */
    private static function categoryOf(array $item): string
    {
        $category = (string) ($item['category'] ?? '');

        return $category !== '' ? $category : mt('director', 'Custom Variables');
    }

    /**
     * Create the collapsible section holding the items of the given category
     *
     * Expanded by default. The id only depends on the category, so a section that
     * has been collapsed stays collapsed on other objects as well.
     *
     * @param string $category
     *
     * @return HtmlElement
     */
    private function createCategorySection(string $category): HtmlElement
    {
        return new HtmlElement(
            'fieldset',
            Attributes::create([
                'id'                    => 'custom-var-category-' . substr(sha1($category), 0, 12),
                'class'                 => ['custom-var-category', 'collapsible'],
                'data-toggle-element'   => 'legend',
                'data-visible-height'   => 0,
                'data-state-collapses'  => true
            ]),
            new HtmlElement('legend', null, Text::create($category))
        );
    }

    /**
     * Prepare the dictionary for display
     *
     * @param array $items
     *
     * @return array
     */
    public static function prepare(array $items): array
    {
        $values = [];
        foreach ($items as $item) {
            if (isset($item['removed'])) {
                continue;
            }

            $values[] = DictionaryItem::prepare($item);
        }

        return $values;
    }

    public function populate($values)
    {
        if (! isset($values['item-count'])) {
            $values['item-count'] = count($values);
        }

        return parent::populate($values);
    }

    /**
     * Get the items to remove from the dictionary
     *
     * @return array
     */
    public function getItemsToRemove(): array
    {
        $this->ensureAssembled();
        $itemsToRemove = $this->getPopulatedValue('items_removed');

        if (! empty($itemsToRemove)) {
            return json_decode($itemsToRemove, true) ?? [];
        }

        return [];
    }

    /**
     * Get the number of rendered DictionaryItem children
     *
     * @return int
     */
    public function getItemCount(): int
    {
        $count = 0;
        foreach ($this->ensureAssembled()->getElements() as $element) {
            if ($element instanceof DictionaryItem) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Whether every child item is unchanged from what it started with
     *
     * @return bool
     */
    public function allChildrenUnchanged(): bool
    {
        foreach ($this->ensureAssembled()->getElements() as $element) {
            if ($element instanceof DictionaryItem && ! $element->ensureAssembled()->isUnchanged()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the dictionary value
     *
     * @param bool $applyUnchangedDefaults Apply the untouched-child type defaults, see DictionaryItem::getItem()
     *
     * @return array
     */
    public function getDictionary(bool $applyUnchangedDefaults = true): array
    {
        $items = [];

        /** @var DictionaryItem $element */
        foreach ($this->ensureAssembled()->getElements() as $element) {
            if ($element instanceof DictionaryItem) {
                $item = $element->ensureAssembled()->getItem($applyUnchangedDefaults);
                if (isset($item['name']) && array_key_exists('value', $item)) {
                    $items[$item['name']] = $item['value'];
                }
            }
        }

        return $items;
    }

    /**
     * Whether any child is inherited from an ancestor
     *
     * Fixed-array/fixed-dictionary inherit as a whole, so one inherited child
     * means the whole thing came from a parent.
     *
     * @return bool
     */
    public function hasInheritedValue(): bool
    {
        foreach ($this->ensureAssembled()->getElements() as $element) {
            if ($element instanceof DictionaryItem && $element->hasInheritedValue()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the submitted required flags, keyed by property name
     *
     * Only carries an entry for items that actually render the required toggle,
     * i.e. properties directly attached to the object being edited.
     *
     * @return bool[]
     */
    public function getRequiredFlags(): array
    {
        $flags = [];

        /** @var DictionaryItem $element */
        foreach ($this->ensureAssembled()->getElements() as $element) {
            if ($element instanceof DictionaryItem) {
                $item = $element->ensureAssembled()->getItem();
                if (isset($item['name']) && array_key_exists('required', $item)) {
                    $flags[$item['name']] = $item['required'];
                }
            }
        }

        return $flags;
    }
}
