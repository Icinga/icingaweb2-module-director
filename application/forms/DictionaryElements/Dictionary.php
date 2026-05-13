<?php

namespace Icinga\Module\Director\Forms\DictionaryElements;

use Icinga\Module\Director\Db\DbUtil;
use ipl\Html\FormElement\FieldsetElement;
use ipl\Html\Html;
use ipl\Web\Widget\EmptyStateBar;

/**
 * @phpstan-type DictionaryDataType array<string, mixed>
 */
class Dictionary extends FieldsetElement
{
    protected $defaultAttributes = ['class' => 'dictionary'];

    /** @var array Dictionary items */
    protected array $items = [];

    /** @var bool Whether to allow removal of item */
    protected bool $allowItemRemoval = false;

    /** @var bool Whether the dictionary is an array */
    protected bool $isArray = false;

    /** @var bool Whether the dictionary is the root */

    public function __construct(string $name, array $items, $attributes = null)
    {
        $this->items = $items;

        parent::__construct($name, $attributes);
    }

    public function setAllowItemRemoval(bool $allow = false): static
    {
        $this->allowItemRemoval = $allow;

        return $this;
    }

    public function getAllowItemRemoval(): bool
    {
        return $this->allowItemRemoval;
    }

    protected function assemble(): void
    {
        $expectedCount = (int) $this->getPopulatedValue('item-count', 0);
        $count = 0;

        // Load previously removed items from the hidden field (no session)
        $removedItemsPopulated = $this->getPopulatedValue('items_removed', '');
        $removedItems = $removedItemsPopulated !== ''
            ? array_fill_keys(explode(', ', $removedItemsPopulated), true)
            : [];

        $remove = null;
        if ($this->allowItemRemoval) {
            while ($count < $expectedCount) {
                $remove = $this->createElement(
                    'submitButton',
                    'remove_' . $count,
                    [
                        'label' => 'Remove Item',
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
                        } elseif (isset($this->items[$clearedItemName]['new'])) {
                            unset($this->items[$clearedItemName]);
                        }
                    }

                    $this->clearPopulatedValue('items_removed');
                    $this->clearPopulatedValue($remove->getName());
                    $this->clearPopulatedValue($count);
                    $this->populate(['items_removed' => implode(', ', array_keys($removedItems))]);

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

        $this->addElement('hidden', 'items_removed', ['value' => implode(', ', array_keys($removedItems))]);
        $this->addElement('hidden', 'items_added', ['value' => implode(', ', $addedItems)]);
        $count = 0;
        foreach ($this->items as $item) {
            $item['uuid'] = DbUtil::binaryResult($item['uuid']);
            if (isset($item['parent_uuid'])) {
                $item['parent_uuid'] = DbUtil::binaryResult($item['parent_uuid']);
            }

            $element = new DictionaryItem((string) $count, $item);

            // Only allow removal of items if the dictionary allows it and the item allows it
            if (
                $this->allowItemRemoval
                && $item['allow_removal']
                && $this->hasElement('remove_' . $count)
            ) {
                $element->setRemoveButton($this->getElement('remove_' . $count));
            }

            $this->addElement($element);
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
            if ($this->allowItemRemoval) {
                $message = $this->translate('No custom variables have been added yet');
            } else {
                $message = $this->translate('No fields configured');
            }

            $this->addHtml(new EmptyStateBar($message));
        }
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

    public function populate($values): static
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
            $itemsToRemove = explode(', ', $itemsToRemove);
        } else {
            $itemsToRemove = [];
        }

        return $itemsToRemove;
    }

    /**
     * Get the number of rendered DictionaryItem children
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
     * Get the dictionary value
     *
     * @return DictionaryDataType
     */
    public function getDictionary(): array
    {
        $items = [];

        /** @var DictionaryItem $element */
        foreach ($this->ensureAssembled()->getElements() as $element) {
            if ($element instanceof DictionaryItem) {
                $item = $element->ensureAssembled()->getItem();
                if (isset($item['name']) && array_key_exists('value', $item)) {
                    $items[$item['name']] = $item['value'];
                }
            }
        }

        return $items;
    }
}
