<?php

namespace Icinga\Module\Director\Windows;

use gipfl\Translation\TranslationHelper;

class JobsMenu extends RemoteMenu
{
    use TranslationHelper;

    public function __construct()
    {
        $this->entries = [
            new MenuEntry(
                $this->translate('Single Machine'),
                $this->translate('Run a specific Job on a single Windows Host'),
                RemoteApi::BASE_URL . '/job?targetType=singleHost',
                'host'
            ),
            new MenuEntry(
                $this->translate('Host List'),
                $this->translate('Run a specific Job on a bunch of Windows Hosts'),
                RemoteApi::BASE_URL . '/job?targetType=hostList',
                'tasks'
            ),
        ];
    }

    public function getTitle(): string
    {
        return $this->translate('Remote Icinga for Windows Job Execution');
    }

    public function getDescription(): string
    {
        return $this->translate('Run predefined Icinga for Windows Jobs on a single or multiple Windows Hosts');
    }
}
