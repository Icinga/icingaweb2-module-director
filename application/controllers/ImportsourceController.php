<?php

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Hook\ImportSourceHook;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Import\Import;
use Icinga\Exception\InvalidPropertyException;
use Icinga\Web\Notification;

class Director_ImportsourceController extends ActionController
{
    public function addAction()
    {
        $this->forward('index', 'importsource', 'director');
    }

    public function editAction()
    {
        $this->forward('index', 'importsource', 'director');
    }

    public function runAction()
    {
        if ($runId = Import::run($id = ImportSource::load($this->params->get('id'), $this->db()))) {
            Notification::success('adf' . $runId);
            $this->redirectNow('director/list/importrun');
        } else {
        }
    }

    public function indexAction()
    {
        $edit = false;

        if ($id = $this->params->get('id')) {
            $edit = true;
        }

        if ($edit) {
            $this->view->title = $this->translate('Edit import source');
            $this->getTabs()->add('edit', array(
                'url'       => 'director/importsource/edit' . '?id=' . $id,
                'label'     => $this->view->title,
            ))->activate('edit');
        } else {
            $this->view->title = $this->translate('Add import source');
            $this->getTabs()->add('add', array(
                'url'       => 'director/importsource/add',
                'label'     => $this->view->title,
            ))->activate('add');
        }

        $form = $this->view->form = $this->loadForm('importSource')
            ->setSuccessUrl('director/list/importsource')
            ->setDb($this->db());

        if ($edit) {
            $form->loadObject($id);
        }

        $form->handleRequest();

        $this->render('object/form', null, true);
    }
}
