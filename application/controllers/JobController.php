<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\DirectorJobForm;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Objects\DirectorJob;
use Icinga\Module\Director\Web\Widget\JobDetails;

class JobController extends ActionController
{
    public function indexAction()
    {
        $job = $this->requireJob();
        $this
            ->addJobTabs($job, 'show')
            ->addTitle($this->translate('Job: %s'), $job->get('job_name'))
            ->content()->add(new JobDetails($job));
    }

    public function addAction()
    {
        $this
            ->addSingleTab($this->translate('New Job'))
            ->addTitle($this->translate('Add a new Job'))
            ->content()->add(
                DirectorJobForm::load()
                    ->setSuccessUrl('director/job')
                    ->setDb($this->db())
                    ->handleRequest()
            );
    }

    public function editAction()
    {
        $job = $this->requireJob();
        $form = DirectorJobForm::load()
            ->setListUrl('director/jobs')
            ->setObject($job)
            ->loadObject($this->params->getRequired('id'))
            ->handleRequest();

        $this
            ->addJobTabs($job, 'edit')
            ->addTitle($this->translate('Job: %s'), $job->get('job_name'))
            ->content()->add($form);
    }

    /**
     * @return DirectorJob
     */
    protected function requireJob()
    {
        return DirectorJob::load($this->params->getRequired('id'), $this->db());
    }

    protected function addJobTabs(DirectorJob $job, $active)
    {
        $id = $job->getId();

        $this->tabs()->add('show', [
            'url'       => 'director/job',
            'urlParams' => ['id' => $id],
            'label'     => $this->translate('Job'),
        ])->add('edit', [
            'url'       => 'director/job/edit',
            'urlParams' => ['id' => $id],
            'label'     => $this->translate('Config'),
        ])->activate($active);

        return $this;
    }
}
