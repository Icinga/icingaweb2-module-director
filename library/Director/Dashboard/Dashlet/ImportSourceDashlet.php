<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Exception;
use Icinga\Module\Director\Auth\Permission;
use Icinga\Module\Director\Objects\ImportSource;

class ImportSourceDashlet extends Dashlet
{
    protected $icon = 'database';

    public function getTitle()
    {
        return $this->translate('Import data sources');
    }

    public function listCssClasses()
    {
        try {
            return $this->fetchStateClass();
        } catch (Exception $e) {
            return 'state-critical';
        }
    }

    public function getSummary()
    {
        return $this->translate(
            'Define and manage imports from various data sources'
        );
    }

    protected function fetchStateClass()
    {
        $srcs = ImportSource::loadAll($this->db);
        if (count($srcs) > 0) {
            $state = 'state-ok';
        } else {
            $state = null;
        }

        foreach ($srcs as $src) {
            if ($src->import_state !== 'in-sync') {
                if ($src->import_state === 'failing') {
                    $state = 'state-critical';
                    break;
                } else {
                    $state = 'state-warning';
                }
            }
        }

        return $state;
    }

    public function getUrl()
    {
        return 'director/importsources';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
