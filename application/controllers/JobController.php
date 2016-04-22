<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Objects\Job;
use Icinga\Data\Filter\Filter;
use Icinga\Web\Notification;
use Icinga\Web\Url;

class JobController extends ActionController
{
    public function addAction()
    {
        $this->indexAction();
    }

    public function editAction()
    {
        $this->indexAction();
    }

    public function runAction()
    {
        // TODO: Form, POST
        $id = $this->params->get('id');
        $job = Job::load($id, $this->db());
        if ($job->run()) {
            Notification::success('Job has successfully been completed');
            $this->redirectNow(
                Url::fromPath(
                    'director/job',
                    array('id' => $id)
                )
            );
        } else {
            Notification::success('Job run failed');
        }
    }

    public function indexAction()
    {
        $form = $this->view->form = $this->loadForm('directorJob')
            ->setSuccessUrl('director/job')
            ->setDb($this->db());

        if ($id = $this->params->get('id')) {
            $this->prepareTabs($id)->activate('edit');
            $form->loadObject($id);
            $this->view->title = sprintf(
                $this->translate('Job %s'),
                $form->getObject()->job_name
            );
        } else {
            $this->view->title = $this->translate('Add job');
            $this->prepareTabs()->activate('add');
        }

        $form->handleRequest();
        $this->setViewScript('object/form');
    }

    protected function prepareTabs($id = null)
    {
        if ($id) {
            return $this->getTabs()->add('edit', array(
                'url'       => 'director/job/edit',
                'urlParams' => array('id' => $id),
                'label'     => $this->translate('Job'),
            ));
        } else {
            return $this->getTabs()->add('add', array(
                'url'       => 'director/job/add',
                'label'     => $this->translate('Job'),
            ));
        }
    }
}
