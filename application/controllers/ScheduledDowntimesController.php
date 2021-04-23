<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectsController;

class ScheduledDowntimesController extends ObjectsController
{
    protected function addObjectsTabs()
    {
        $res = parent::addObjectsTabs();
        $this->tabs()->remove('index');
        $this->tabs()->remove('templates');
        return $res;
    }

    protected function getTable()
    {
        return parent::getTable()
            ->setBaseObjectUrl('director/scheduled-downtime');
    }

    protected function getApplyRulesTable()
    {
        return parent::getApplyRulesTable()->createLinksWithNames();
    }

    public function getType()
    {
        return 'scheduledDowntime';
    }

    public function getBaseObjectUrl()
    {
        return 'scheduled-downtime';
    }

    protected function assertApplyRulePermission()
    {
        return $this->assertPermission('director/scheduled-downtimes');
    }

    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/scheduled-downtimes');
    }
}
