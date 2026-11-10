<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Forms\DictionaryElements;

use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlElement;
use ipl\Html\Text;

/**
 * A foldable box with the custom variable rows that come from one source
 */
class DictionaryGroup extends BaseHtmlElement
{
    protected $tag = 'details';

    protected $defaultAttributes = ['class' => 'custom-variable-group'];

    /** @var DictionaryItem[] */
    protected array $items = [];

    /**
     * Create a new DictionaryGroup
     *
     * @param string $label The heading of the box
     * @param bool   $open  Whether the box starts open
     */
    public function __construct(protected string $label, protected bool $open = true)
    {
    }

    /**
     * Add a row
     *
     * @param DictionaryItem $item
     * @param int            $index The row's index in the dictionary, part of the id of its wrapper
     *
     * @return $this
     */
    public function addItem(DictionaryItem $item, int $index): static
    {
        $this->items[] = $item;
        $this->addHtml(Html::tag('div', ['id' => 'custom-var-row-' . $index, 'class' => 'custom-var-row'], $item));

        return $this;
    }

    /**
     * Check whether the box has rows
     *
     * @return bool
     */
    public function hasItems(): bool
    {
        return ! empty($this->items);
    }

    protected function assemble(): void
    {
        $this->addHtml(new HtmlElement('summary', null, Text::create($this->label)));
    }

    protected function registerAttributeCallbacks(Attributes $attributes): void
    {
        $attributes->registerAttributeCallback('open', fn () => $this->open ? true : null);
    }
}
