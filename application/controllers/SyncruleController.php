<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Director\Import\Sync;
use Icinga\Data\Filter\Filter;
use Icinga\Web\Notification;

class SyncruleController extends ActionController
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
        $sync = new Sync(SyncRule::load($this->params->get('id'), $this->db()));
        if ($runId = $sync->apply()) {
            Notification::success('Source has successfully been synchronized');
            $this->redirectNow('director/list/syncrule');
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
            $this->view->title = $this->translate('Edit sync rule');
            $this->getTabs()->add('edit', array(
                'url'       => 'director/syncrule/edit',
                'urlParams' => array('id' => $id),
                'label'     => $this->view->title,
            ))->add('property', array(
                'label' => $this->translate('Properties'),
                'url'   => 'director/syncrule/property',
                'urlParams' => array('rule_id' => $id)
            ))->activate('edit');
        } else {
            $this->view->title = $this->translate('Add sync rule');
            $this->getTabs()->add('add', array(
                'url'       => 'director/syncrule/add',
                'label'     => $this->view->title,
            ))->activate('add');
        }

        $form = $this->view->form = $this->loadForm('syncRule')
            ->setSuccessUrl('director/list/syncrule')
            ->setDb($this->db());

        if ($edit) {
            $form->loadObject($id);
        }

        $form->handleRequest();

        $this->render('object/form', null, true);
    }

    public function propertyAction()
    {
        $this->view->stayHere = true;
        $id = $this->params->get('rule_id');

        $this->view->addLink = $this->view->icon('plus')
            . ' '
            .  $this->view->qlink(
                $this->translate('Add sync property rule'),
                'director/syncrule/addproperty',
                array('rule_id' => $id)
            );
        $this->getTabs()->add('edit', array(
            'url'       => 'director/syncrule/edit',
            'urlParams' => array('id' => $id),
            'label'     => $this->translate('Edit sync rule'),
        ))->add('property', array(
            'label' => $this->translate('Properties'),
            'url'   => 'director/syncrule/property',
            'urlParams' => array('rule_id' => $id)
        ))->activate('property');

        $this->view->title = $this->translate('Sync properties: ');
        $this->view->table = $this->loadTable('syncproperty')
            ->enforceFilter(Filter::where('rule_id', $id))
            ->setConnection($this->db());
        $this->render('list/table', null, true);
    }

    public function editpropertyAction()
    {
        $this->addpropertyAction();
    }

    public function addpropertyAction()
    {
        $this->view->stayHere = true;
        $edit = false;

        if ($id = $this->params->get('id')) {
            $edit = true;
        }

        $form = $this->view->form = $this->loadForm('syncProperty')->setDb($this->db());

        if ($edit) {
            $form->loadObject($id);
            $rule_id = $form->getObject()->rule_id;
            $form->setRule(SyncRule::load($rule_id, $this->db()));
        } elseif ($rule_id = $this->params->get('rule_id')) {
            $form->setRule(SyncRule::load($rule_id, $this->db()));
        }
        $form->setSuccessUrl('director/syncrule/property', array('rule_id' => $rule_id));

        $form->handleRequest();

        $tabs = $this->getTabs()->add('edit', array(
            'url'       => 'director/syncrule/edit',
            'urlParams' => array('id' => $rule_id),
            'label'     => $this->translate('Edit sync rule'),
        ));

        if ($edit) {
            $tabs->add('property', array(
                'label' => $this->translate('Properties'),
                'url'   => 'director/syncrule/property',
                'urlParams' => array('rule_id' => $rule_id)
            ));
        } else {
            $tabs->add('property', array(
                'label' => $this->translate('Properties'),
                'url'   => 'director/syncrule/property',
                'urlParams' => array('rule_id' => $rule_id)
            ));
        }

        $tabs->activate('property');

        $this->view->title = $this->translate('Sync property'); // add/edit
        $this->view->table = $this->loadTable('syncproperty')
            ->enforceFilter(Filter::where('rule_id', $rule_id))
            ->setConnection($this->db());
        $this->render('list/table', null, true);
    }
}
