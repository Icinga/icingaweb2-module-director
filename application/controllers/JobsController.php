<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\DirectorObject\Automation\ImportExport;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Table\JobTable;
use Icinga\Module\Director\Web\Tabs\ImportTabs;

class JobsController extends ActionController
{
    protected $isApified = true;

    public function indexAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            switch (strtolower($this->getRequest()->getMethod())) {
                case 'get':
                    $this->sendExport();
                    break;
                case 'post':
                    $this->acceptImport($this->getRequest()->getRawBody());
                    break;
                case 'delete':
                    $this->deleteJob($this->params->get('name'));
                    break;
                default:
                    $this->sendUnsupportedMethod();
            }

            return;
        }

        $this->addTitle($this->translate('Jobs'))
            ->setAutorefreshInterval(10)
            ->addAddLink($this->translate('Add a new Job'), 'director/job/add')
            ->tabs(new ImportTabs())->activate('jobs');

        (new JobTable($this->db()))->renderTo($this);
    }

    protected function deleteJob($name)
    {
        $db = $this->db()->getDbAdapter();
        $id = (int) $db->fetchOne(
            $db->select()->from('director_job', 'id')->where('job_name = ?', $name)
        );
        if (!$id) {
            $this->sendJson($this->getResponse(), (object)[]);
            return;
        }
        $db->delete('director_job_setting', ['job_id = ?' => $id]);
        $db->delete('director_job', ['id = ?' => $id]);
        $this->sendJson($this->getResponse(), (object)[]);
    }

    protected function acceptImport($raw)
    {
        $count = (new ImportExport($this->db()))->unserializeJobs(json_decode($raw));
        $this->sendJson($this->getResponse(), ['imported' => $count]);
    }

    protected function sendExport()
    {
        $this->sendJson(
            $this->getResponse(),
            (new ImportExport($this->db()))->serializeAllJobs()
        );
    }
}
