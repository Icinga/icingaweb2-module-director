<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Objects\SyncRule;

class SyncpropertyController extends ActionController
{
    public function addAction()
    {
        $this->indexAction();
    }

    public function editAction()
    {
        $this->indexAction();
    }

    public function indexAction()
    {
        $edit = false;

        if ($id = $this->params->get('id')) {
            $edit = true;
        }

        if ($edit) {
            $this->view->title = $this->translate('Edit sync property rule');
            $this->getTabs()->add('edit', array(
                'url'       => 'director/syncproperty/edit' . '?id=' . $id,
                'label'     => $this->view->title,
            ))->activate('edit');
        } else {
            $this->view->title = $this->translate('Add sync property rule');
            $this->getTabs()->add('add', array(
                'url'       => 'director/syncproperty/add',
                'label'     => $this->view->title,
            ))->activate('add');
        }

        $form = $this->view->form = $this->loadForm('syncProperty')
            ->setSuccessUrl('director/list/syncproperty')
            ->setDb($this->db());

        if ($edit) {
            $form->loadObject($id);
            $form->setRule(SyncRule::load($form->getObject()->rule_id, $this->db()));
        } elseif ($rule_id = $this->params->get('rule_id')) {
            $form->setRule(SyncRule::load($rule_id, $this->db()));
        }

        $form->handleRequest();

        $this->render('object/form', null, true);
    }
}
