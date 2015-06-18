<?php

use Icinga\Module\Director\ActionController;

use Icinga\Module\Director\IcingaConfig\IcingaConfig;

class Director_ShowController extends ActionController
{
    public function activitylogAction()
    {
        if ($id = $this->params->get('id')) {
            $this->view->entry = $this->db()->fetchActivityLogEntryById($id);
        } elseif ($checksum = $this->params->get('checksum')) {
            $this->view->entry = $this->db()->fetchActivityLogEntry(pack('H*', $checksum));
        }

        $this->view->title = $this->translate('Activity');
    }
}
