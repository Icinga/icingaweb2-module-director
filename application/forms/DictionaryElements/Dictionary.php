<?php

namespace Icinga\Module\Director\Forms\DictionaryElements;

use ipl\Html\FormElement\FieldsetElement;

/**
 * @phpstan-type DictionaryDataType array<string, mixed>
 */
class Dictionary extends FieldsetElement
{
    protected $defaultAttributes = ['class' => 'dictionary'];

    /** @var array Dictionary items */
    protected array $items = [];

    public function __construct(string $name, array $items, $attributes = null)
    {
        $this->setItems($items);

        parent::__construct($name, $attributes);
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
        $count = count($this->items);
        for ($i = 0; $i < $count; $i++) {
            $this->addElement(new DictionaryItem($i, $this->items[$i]));
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
                if (! empty($item['name']) && array_key_exists('value', $item)) {
                    $items[$item['name']] = $item['value'];
                }
            }
        }

        return $items;
    }
}
