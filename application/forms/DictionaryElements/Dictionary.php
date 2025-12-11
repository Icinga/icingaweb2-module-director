<?php

namespace Icinga\Module\Director\Forms\DictionaryElements;

use Icinga\Web\Session;
use ipl\Html\FormElement\FieldsetElement;
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

    public function __construct(string $name, array $items, $attributes = null)
    {
        $this->setItems($items);

        parent::__construct($name, $attributes);
    }

    public function setAllowItemRemoval(bool $allow = false): static
    {
        $this->allowItemRemoval = $allow;

        return $this;
    }

    /**
     * Set the dictionary items
     *
     * @param array $items
     *
     * @return $this
     */
    public function setItems(array $items): static
    {
        $this->items = $items;

        return $this;
    }

    protected function assemble(): void
    {
        $expectedCount = (int) $this->getPopulatedValue('item-count', 0);
        $count = 0;

        $removedItems = [];
        if ($this->allowItemRemoval) {
            $removedItems = Session::getSession()->getNamespace('director.variables')->get('removed-properties', []);
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
                    $clearedItemName = null;
                    if (isset($removedValue['name'])) {
                        $clearedItemName = $removedValue['name'];
                        $addedProperties = Session::getSession()->getNamespace('director.variables')
                                             ->get('added-properties');

                        if ($addedProperties !== null) {
                            unset($addedProperties[$clearedItemName]);
                            Session::getSession()->getNamespace('director.variables')->set('added-properties', $addedProperties);
                        }

                        $removedItems[$clearedItemName] = $this->items[$clearedItemName]['uuid'];
                    }

                    $this->clearPopulatedValue('items_removed');
                    $this->clearPopulatedValue($remove->getName());
                    $this->clearPopulatedValue($count);
                    Session::getSession()->getNamespace('director.variables')->set('removed-properties', $removedItems);
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

        $this->addElement('hidden', 'items_removed');
        $this->addElement('hidden', 'items_added', ['value' => implode(', ', $addedItems)]);
        $count = 0;
        foreach ($this->items as $item) {
            $element = new DictionaryItem($count, $item);

            // Only allow removal of items if the dictionary allows it and the item allows it
            if (
                $this->allowItemRemoval
                && $this->hasElement('remove_' . $count)
            ) {
                $element->setRemoveButton($this->getElement('remove_' . $count));
            }

            $this->addElement($element);
            $count++;
        }

        $this->clearPopulatedValue('item-count');
        $this->addElement('hidden', 'item-count', ['ignore' => true, 'value' => $count]);
        if ($count === 0) {
            if ($this->allowItemRemoval) {
                $message = $this->translate('All custom properties in the object has been removed');
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
