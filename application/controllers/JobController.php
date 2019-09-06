<?php

namespace Icinga\Module\Director\Controllers;

use gipfl\IcingaWeb2\Link;
use Icinga\Module\Director\Forms\DirectorJobForm;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Objects\DirectorJob;
use Icinga\Module\Director\Web\Widget\JobDetails;

class JobController extends ActionController
{
    /**
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function indexAction()
    {
        $job = $this->requireJob();
        $this
            ->addJobTabs($job, 'show')
            ->addTitle($this->translate('Job: %s'), $job->get('job_name'))
            ->addToBasketLink()
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

    /**
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function editAction()
    {
        $job = $this->requireJob();
        $form = DirectorJobForm::load()
            ->setListUrl('director/jobs')
            ->setObject($job)
            ->handleRequest();

        $this
            ->addJobTabs($job, 'edit')
            ->addTitle($this->translate('Job: %s'), $job->get('job_name'))
            ->addToBasketLink()
            ->content()->add($form);
    }

    /**
     * @return DirectorJob
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Exception\MissingParameterException
     */
    protected function requireJob()
    {
        return DirectorJob::loadWithAutoIncId((int) $this->params->getRequired('id'), $this->db());
    }

    /**
     * @return $this
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function addToBasketLink()
    {
        $job = $this->requireJob();
        $this->actions()->add(Link::create(
            $this->translate('Add to Basket'),
            'director/basket/add',
            [
                'type'  => 'DirectorJob',
                'names' => $job->getUniqueIdentifier()
            ],
            ['class' => 'icon-tag']
        ));

        return $this;
    }

    protected function addJobTabs(DirectorJob $job, $active)
    {
        $id = $job->get('id');

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
