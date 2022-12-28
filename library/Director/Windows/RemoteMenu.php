<?php

namespace Icinga\Module\Director\Windows;

use JsonSerializable;

abstract class RemoteMenu implements JsonSerializable
{
    /** @var MenuEntry[] */
    protected $entries = [];

    abstract public function getTitle(): string;

    abstract public function getDescription(): string;

    /**
     * @return MenuEntry[]
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'ResponseType' => 'Menu',
            'Title'        => $this->getTitle(),
            'Description'  => $this->getDescription(),
            'Entries'      => $this->getEntries()
        ];
    }
}
