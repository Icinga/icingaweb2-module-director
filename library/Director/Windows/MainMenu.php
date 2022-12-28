<?php

namespace Icinga\Module\Director\Windows;

use gipfl\Translation\TranslationHelper;

class MainMenu extends RemoteMenu
{
    use TranslationHelper;

    public function __construct()
    {
        $this->entries = [
            new MenuEntry(
                $this->translate('Job Orchestration'),
                $this->translate('Run a specific Job on a single Windows Host or on a bunch of them'),
                RemoteApi::BASE_URL . '/jobs',
                'file-word'
            ),
            new MenuEntry(
                $this->translate('Inventory'),
                $this->translate('Icinga for Windows Hosts Inventory'),
                RemoteApi::BASE_URL . '/inventory',
                'database'
            ),
        ];
    }

    public function getTitle(): string
    {
        return $this->translate('Icinga for Windows');
    }

    public function getDescription(): string
    {
        return $this->translate("From here, you're allowed to control the world");
    }
}
