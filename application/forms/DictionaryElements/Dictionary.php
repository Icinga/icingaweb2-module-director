<?php

namespace Icinga\Module\Director\Forms\DictionaryElements;

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
        $count = 0;
        foreach ($this->items as $item) {
            $element = new DictionaryItem($count, $item);

            if ($this->allowItemRemoval && isset($item['allow_removal'])) {
                $element->setRemovable($item['allow_removal']);
            }

            $this->addElement($element);
            $count++;
        }

        if ($count === 0) {
            $this->addHtml(new EmptyStateBar($this->translate('No fields configured')));
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

    public function getItemsToRemove(): array
    {
        $itemsToRemove = [];

        /** @var DictionaryItem $element */
        foreach ($this->ensureAssembled()->getElements() as $element) {
            if ($element instanceof DictionaryItem) {
                $item = $element->ensureAssembled()->getItem();
                if (isset($item['delete']) && $item['delete'] === 'y') {
                    $itemsToRemove[$item['name']] = $this->items[$item['name']]['uuid'];
                }
            }
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
