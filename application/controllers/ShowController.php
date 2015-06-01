<?php

use Icinga\Module\Director\ActionController;

class Director_ShowController extends ActionController
{
    public function activitylogAction()
    {
        if ($id = $this->params->get('id')) {
            $this->view->entry = $this->db()->fetchActivityLogEntry($id);
            $this->view->title = $this->translate('Activity');
        }
    }
}
