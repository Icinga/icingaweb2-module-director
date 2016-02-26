<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;

class DatalistentryController extends ActionController
{
    public function addAction()
    {
        $this->indexAction();
    }

    public function editAction()
    {
        $this->indexAction(true);
    }

    public function indexAction($edit = false)
    {
        $request = $this->getRequest();

        $listId = $this->params->get('list_id');
        $this->view->lastId = $listId;

        if ($this->params->get('list_id') && $entryName = $this->params->get('entry_name')) {
            $edit = true;
        }

        if ($edit) {
            $this->view->title = $this->translate('Edit entry');
            $this->getTabs()->add('editentry', array(
                'url'       => 'director/datalistentry/edit' . '?list_id=' . $listId . '&entry_name=' . $entryName,
                'label'     => $this->view->title,
            ))->activate('editentry');
        } else {
            $this->view->title = $this->translate('Add entry');
            $this->getTabs()->add('addlistentry', array(
                'url'       => 'director/datalistentry/add' . '?list_id=' . $listId,
                'label'     => $this->view->title,
            ))->activate('addlistentry');
        }

        $form = $this->view->form = $this->loadForm('directorDatalistentry')
            ->setListId($listId)
            ->setSuccessUrl('director/datalistentry' . '?list_id=' . $listId)
            ->setDb($this->db());

        if ($request->isPost()) {
            $listId = $request->getParam('list_id');
            $entryName = $request->getParam('entry_name');
        }

        if ($edit) {
            $form->loadObject(array('list_id' => $listId, 'entry_name' => $entryName));
            if ($el = $form->getElement('entry_name')) {
                // TODO: Doesn't work without setup
                $el->setAttribs(array('readonly' => true));
            }
        }

        $form->handleRequest();

        $this->render('object/form', null, true);
    }
}
