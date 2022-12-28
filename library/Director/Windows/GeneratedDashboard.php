<?php

namespace Icinga\Module\Director\Windows;

use Icinga\Module\Director\Dashboard\Dashboard;
use Icinga\Module\Director\Db;

class GeneratedDashboard extends Dashboard
{
    /** @var ?RemoteMenu */
    protected $menu;

    public static function create(RemoteMenu $menu, Db $db)
    {
        $self = new static();
        $self->db = $db;
        $self->menu = $menu;
        $self->name = $menu->getTitle();

        return $self;
    }

    public function getTitle()
    {
        return $this->menu->getTitle();
    }

    public function getDescription()
    {
        return $this->menu->getDescription();
    }

    public function loadDashlets()
    {
        if ($this->menu) {
            foreach ($this->menu->getEntries() as $entry) {
                $this->dashlets[] = new GeneratedDashlet($entry, $this->db);
            }
        }
    }
}
