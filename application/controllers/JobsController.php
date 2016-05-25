<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;

class JobsController extends ActionController
{
    public function indexAction()
    {
        $this->setAutoRefreshInterval(10);
        $this->view->title = $this->translate('Jobs');
        $this->setImportTabs()->activate('jobs');

        $this->view->addLink = $this->view->qlink(
            $this->translate('Add'),
            'director/job',
            null,
            array('class' => 'icon-plus')
        );

        $this->view->table = $this->applyPaginationLimits(
            $this->loadTable('job')
                ->setConnection($this->db())
        );
        $this->setViewScript('list/table');

    }
}
