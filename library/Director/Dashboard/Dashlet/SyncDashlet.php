<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Exception;
use Icinga\Module\Director\Objects\SyncRule;

class SyncDashlet extends Dashlet
{
    protected $icon = 'flapping';

    public function getTitle()
    {
        return $this->translate('Synchronize');
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
            'Define how imported data should be synchronized with Icinga'
        );
    }

    protected function fetchStateClass()
    {
        $syncs = SyncRule::loadAll($this->db);
        if (count($syncs) > 0) {
            $state = 'state-ok';
        } else {
            $state = null;
        }

        foreach ($syncs as $sync) {
            if ($sync->sync_state !== 'in-sync') {
                if ($sync->sync_state === 'failing') {
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
        return 'director/list/syncrule';
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }
}
