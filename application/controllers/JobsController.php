<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Table\JobTable;
use Icinga\Module\Director\Web\Tabs\ImportTabs;

class JobsController extends ActionController
{
    public function indexAction()
    {
        $this->addTitle($this->translate('Jobs'))
            ->setAutorefreshInterval(10)
            ->addAddLink($this->translate('Add a new Job'), 'director/job/add')
            ->tabs(new ImportTabs())->activate('jobs');

        (new JobTable($this->db()))->renderTo($this);
    }
}
