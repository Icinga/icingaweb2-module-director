<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Director\Objects\SyncRun;
use Icinga\Module\Director\Import\Sync;
use Icinga\Data\Filter\Filter;
use Icinga\Web\Notification;
use Icinga\Web\Url;

class SyncruleController extends ActionController
{
    public function indexAction()
    {
        $this->setAutoRefreshInterval(10);
        $id = $this->params->get('id');
        $this->prepareRuleTabs($id)->activate('show');
        $rule = $this->view->rule = SyncRule::load($id, $this->db());
        $this->view->title = sprintf(
            $this->translate('Sync rule: %s'),
            $rule->rule_name
        );

        if ($lastRunId = $rule->getLastSyncRunId()) {
            $this->loadSyncRun($lastRunId);

        } else {
            $this->view->run = null;
        }
        $this->view->checkForm = $this
            ->loadForm('syncCheck')
            ->setSyncRule($rule)
            ->handleRequest();

        $this->view->runForm = $this
            ->loadForm('syncRun')
            ->setSyncRule($rule)
            ->handleRequest();
    }

    public function addAction()
    {
        $this->editAction();
    }

    public function editAction()
    {
        $form = $this->view->form = $this->loadForm('syncRule')
            ->setSuccessUrl('director/list/syncrule')
            ->setDb($this->db());

        if ($id = $this->params->get('id')) {
            $this->prepareRuleTabs($id)->activate('edit');
            $form->loadObject($id);
            $this->view->title = sprintf(
                $this->translate('Sync rule: %s'),
                $form->getObject()->rule_name
            );
        } else {
            $this->view->title = $this->translate('Add sync rule');
            $this->prepareRuleTabs()->activate('add');
        }

        $form->handleRequest();
        $this->setViewScript('object/form');
    }

    public function runAction()
    {
        $id = $this->params->get('id');
        $rule = SyncRule::load($id, $this->db());
        $changed = $rule->applyChanges();

        if ($changed) {
            $runId = $rule->getCurrentSyncRunId();
            Notification::success('Source has successfully been synchronized');
            $this->redirectNow(
                Url::fromPath(
                    'director/syncrule/history',
                    array(
                        'id'     => $id,
                        'run_id' => $runId
                    )
                )
            );
        } elseif ($rule->sync_state === 'in-sync') {
            Notification::success('Nothing changed, rule is in sync');
        } else {
            Notification::error('Synchronization failed');
        }

        $this->redirectNow('director/syncrule?id=' . $id);
    }

    public function propertyAction()
    {
        $this->view->stayHere = true;

        $db = $this->db();
        $id = $this->params->get('rule_id');
        $rule = SyncRule::load($id, $db);

        $this->prepareRuleTabs($id)->activate('property');

        $this->view->addLink = $this->view->qlink(
            $this->translate('Add sync property rule'),
            'director/syncrule/addproperty',
            array('rule_id' => $id),
            array('class' => 'icon-plus')
        );

        $this->view->title = $this->translate('Sync properties') . ': ' . $rule->rule_name;
        $this->view->table = $this->loadTable('syncproperty')
            ->enforceFilter(Filter::where('rule_id', $id))
            ->setConnection($this->db());
        $this->setViewScript('list/table');
    }

    public function editpropertyAction()
    {
        $this->addpropertyAction();
    }

    public function addpropertyAction()
    {
        $this->view->stayHere = true;
        $edit = false;

        $db = $this->db();
        $ruleId = $this->params->get('rule_id');
        $rule = SyncRule::load($ruleId, $db);

        if ($id = $this->params->get('id')) {
            $edit = true;
        }

        $this->view->addLink = $this->view->qlink(
            $this->translate('back'),
            'director/syncrule/property',
            array('rule_id' => $ruleId),
            array('class' => 'icon-left-big')
        );

        $form = $this->view->form = $this->loadForm('syncProperty')->setDb($db);

        if ($edit) {
            $form->loadObject($id);
            $rule_id = $form->getObject()->rule_id;
            $form->setRule(SyncRule::load($rule_id, $db));
        } elseif ($rule_id = $this->params->get('rule_id')) {
            $form->setRule(SyncRule::load($rule_id, $db));
        }

        $form->setSuccessUrl('director/syncrule/property', array('rule_id' => $rule_id));
        $form->handleRequest();

        $this->prepareRuleTabs($rule_id)->activate('property');

        if ($edit) {
            $this->view->title = sprintf(
                $this->translate('Sync "%s": %s'),
                $form->getObject()->destination_field,
                $rule->rule_name
            );
        } else {
            $this->view->title = sprintf(
                $this->translate('Add sync property: %s'),
                $rule->rule_name
            );
        }

        $this->view->table = $this->loadTable('syncproperty')
            ->enforceFilter(Filter::where('rule_id', $rule_id))
            ->setConnection($this->db());
        $this->setViewScript('list/table');
    }

    public function historyAction()
    {
        $this->view->stayHere = true;

        $db = $this->db();
        $id = $this->params->get('id');
        $rule = SyncRule::load($id, $db);

        $this->prepareRuleTabs($id)->activate('history');
        $this->view->title = $this->translate('Sync history') . ': ' . $rule->rule_name;
        $this->view->table = $this->loadTable('syncRun')
            ->enforceFilter(Filter::where('rule_id', $id))
            ->setConnection($this->db());

        if ($runId = $this->params->get('run_id')) {
            $this->loadSyncRun($runId);
        }
    }

    protected function loadSyncRun($id)
    {
        $db = $this->db();
        $this->view->run = SyncRun::load($id, $db);
        if ($this->view->run->last_former_activity !== null) {
            $this->view->formerId = $db->fetchActivityLogIdByChecksum(
                $this->view->run->last_former_activity
            );

            $this->view->lastId = $db->fetchActivityLogIdByChecksum(
                $this->view->run->last_related_activity
            );
        }
    }

    protected function prepareRuleTabs($ruleId = null)
    {
        if ($ruleId) {
            $tabs = $this->getTabs()->add('show', array(
                'url'       => 'director/syncrule',
                'urlParams' => array('id' => $ruleId),
                'label'     => $this->translate('Sync rule'),
            ))->add('edit', array(
                'url'       => 'director/syncrule/edit',
                'urlParams' => array('id' => $ruleId),
                'label'     => $this->translate('Modify'),
            ))->add('property', array(
                'label' => $this->translate('Properties'),
                'url'   => 'director/syncrule/property',
                'urlParams' => array('rule_id' => $ruleId)
            ));

            $tabs->add('history', array(
                'label' => $this->translate('History'),
                'url'   => 'director/syncrule/history',
                'urlParams' => array('id' => $ruleId)
            ));

            return $tabs;
        } else {
            return $this->getTabs()->add('add', array(
                'url'       => 'director/syncrule/add',
                'label'     => $this->translate('Sync rule'),
            ));
        }
    }
}
