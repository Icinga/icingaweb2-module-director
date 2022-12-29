<?php

namespace Icinga\Module\Director\Windows;

use Icinga\Module\Director\Dashboard\Dashlet\Dashlet;
use Icinga\Module\Director\Db;

class GeneratedDashlet extends Dashlet
{
    /** @var MenuEntry */
    protected $entry;

    public function __construct(MenuEntry $entry, Db $db)
    {
        $this->entry = $entry;
        parent::__construct($db);
    }

    public function getTitle(): string
    {
        return $this->entry->label;
    }

    public function getUrl(): string
    {
        return $this->entry->url;
    }

    public function getSummary(): string
    {
        return $this->entry->description;
    }

    public function getIconName(): string
    {
        return $this->entry->icon ?? 'help';
    }
}
