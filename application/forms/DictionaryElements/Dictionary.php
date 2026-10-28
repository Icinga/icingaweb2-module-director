<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Forms\DictionaryElements;

use Icinga\Module\Director\CustomVariable\ObjectCustomPropertySources;
use Icinga\Module\Director\Db\DbUtil;
use ipl\Html\FormElement\FieldsetElement;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlElement;
use ipl\Html\ValidHtml;
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

    /** @var array The boxes the rows are drawn in, keyed by section */
    private array $sections = [];

    /** @var DictionaryItem[] The rows drawn in the command box */
    private array $commandRows = [];

    /** @var ?string Heading for the object's own rows */
    private ?string $ownHeading = null;

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
     * Set the heading for the rows the object itself added
     *
     * Without a heading the own rows are drawn without a box.
     *
     * @param ?string $heading
     *
     * @return $this
     */
    public function setOwnHeading(?string $heading): static
    {
        $this->ownHeading = $heading;

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
        $this->sections = [];
        $this->commandRows = [];
        $swapTargets = [];
        foreach ($this->items as $item) {
            $item['uuid'] = DbUtil::binaryResult($item['uuid']);
            if (isset($item['parent_uuid'])) {
                $item['parent_uuid'] = DbUtil::binaryResult($item['parent_uuid']);
            }

            $element = (new DictionaryItem((string) $count, $item))
                ->setAllowRequiredToggle($this->allowRequiredToggle)
                ->setShowValue($this->showValues);

            // Only allow removal of items if the dictionary allows it and the item allows it
            if (
                $this->allowItemRemoval
                && $item['allow_removal']
                && $this->hasElement('remove_' . $count)
            ) {
                $element->setRemoveButton($this->getElement('remove_' . $count));
            }

            // Put the row in its section box instead of straight into the dictionary.
            // Its number still follows the data order.
            $this->registerElement($element)->decorate($element);

            $section = $item['section'] ?? null;
            if ($section !== null && $section['kind'] === ObjectCustomPropertySources::COMMAND) {
                $this->commandRows[] = $element;
            }

            $row = $element;
            if (
                $this->allowItemRemoval
                && $section !== null
                && $section['kind'] !== ObjectCustomPropertySources::OWN
            ) {
                // Adding this variable to the object itself swaps the row for one in the own box
                $row = Html::tag(
                    'div',
                    ['id' => self::getInheritedRowId($item['key_name']), 'class' => 'dictionary-row'],
                    $element
                );
                $swapTargets[] = Html::tag('div', ['id' => self::getSwapTargetId($item['key_name'])]);
            }

            $this->getSectionBody($section)->addHtml($row);
            $count++;
        }

        if ($this->allowItemRemoval) {
            foreach ($swapTargets as $swapTarget) {
                $this->getSectionBody(null)->addHtml($swapTarget);
            }

            // A new variable belongs to the object itself, so the slot sits in its box
            $newVarSlot = Html::tag('div', ['id' => 'new-var-slot-' . $count]);
            $this->getSectionBody(null)->addHtml($newVarSlot);
        }

        // The slot makes the own box exist even without own rows, so a new variable lands under the heading
        $ownId = ObjectCustomPropertySources::OWN . ':';
        if (isset($this->sections[$ownId]) && $this->ownHeading !== null) {
            $this->sections[$ownId]['html'] = $this->createGroup(
                ['dictionary-section', 'own', 'collapsible'],
                $this->ownHeading,
                HtmlElement::create('div', ['class' => 'dictionary-section-body'], $this->sections[$ownId]['body']),
                true
            );
        }

        uasort($this->sections, fn(array $a, array $b) => $a['order'] <=> $b['order']);
        foreach ($this->sections as $section) {
            $this->addHtml($section['html']);
        }

        $this->clearPopulatedValue('item-count');
        $itemCountInput = $this->createElement('hidden', 'item-count', ['ignore' => true, 'value' => $count]);
        $this->registerElement($itemCountInput);
        $this->addHtml(Html::tag('div', ['id' => $this->getName() . '-item-count'], $itemCountInput));

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
     * Get the id of the wrapper around a row that comes from a template or the command
     *
     * @param string $key
     *
     * @return string
     */
    public static function getInheritedRowId(string $key): string
    {
        return 'dictionary-row-' . md5($key);
    }

    /**
     * Get the id of the container in the own box that receives the row swapped in
     *
     * @param string $key
     *
     * @return string
     */
    public static function getSwapTargetId(string $key): string
    {
        return 'swap-target-' . md5($key);
    }

    /**
     * Get the box the rows of the given section are drawn in
     *
     * @param ?array $section The section the rows belong to, null for the object itself
     *
     * @return HtmlDocument
     */
    private function getSectionBody(?array $section): HtmlDocument
    {
        if ($section === null || $section['kind'] === ObjectCustomPropertySources::OWN) {
            // The object's own rows always come first, whatever its name is
            $section = ['kind' => ObjectCustomPropertySources::OWN, 'name' => '', 'order' => -1];
        }

        $id = $section['kind'] . ':' . $section['name'];
        if (! isset($this->sections[$id])) {
            $this->sections[$id] = ['order' => $section['order']] + $this->createSection($section);
        }

        return $this->sections[$id]['body'];
    }

    /**
     * Create the box for one section
     *
     * @param array $section The kind, name and order of the section
     *
     * @return array{html: ValidHtml, body: HtmlDocument}
     */
    private function createSection(array $section): array
    {
        if ($section['kind'] === ObjectCustomPropertySources::OWN) {
            // No box and no heading, so rows without a section look as they always did
            $body = new HtmlDocument();

            return ['html' => $body, 'body' => $body];
        }

        $body = HtmlElement::create('div', ['class' => 'dictionary-section-body']);
        if ($section['kind'] === ObjectCustomPropertySources::COMMAND) {
            $html = $this->createGroup(
                ['dictionary-section', 'command', 'collapsible'],
                sprintf($this->translate('Added by command "%s"'), $section['name']),
                $body,
                false
            );

            // The rows get validated after the box is built, so a failed row opens it when it renders
            $html->getAttributes()->registerAttributeCallback('open', function () {
                foreach ($this->commandRows as $row) {
                    if ($row->hasBeenValidated() && ! $row->isValid()) {
                        return true;
                    }
                }

                return null;
            });
        } else {
            // A command can import a plain command too, only a command template is called a template
            $isPlainCommand = ($section['type'] ?? null) === 'command'
                && ($section['object_type'] ?? 'template') !== 'template';
            $heading = $isPlainCommand
                ? $this->translate('Added by command "%s"')
                : $this->translate('Added by template "%s"');
            $html = $this->createGroup(
                ['dictionary-section', 'template', 'collapsible'],
                sprintf($heading, $section['name']),
                $body,
                true
            );
        }

        return ['html' => $html, 'body' => $body];
    }

    /**
     * Create a foldable box around the given rows
     *
     * A details element and not a fieldset, so the input names of the rows stay as they are.
     *
     * @param string[] $classes
     * @param string $heading
     * @param ValidHtml $body
     * @param bool $open Whether the box starts unfolded
     *
     * @return HtmlElement
     */
    private function createGroup(array $classes, string $heading, ValidHtml $body, bool $open): HtmlElement
    {
        // The collapsible class lets Icinga Web fold it. A closed box gets no open attribute at all
        $attributes = array_filter(['class' => $classes, 'open' => $open, 'data-no-persistence' => true]);

        return HtmlElement::create('details', $attributes, [HtmlElement::create('summary', null, $heading), $body]);
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
