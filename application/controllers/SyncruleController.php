<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\SyncCheckForm;
use Icinga\Module\Director\Forms\SyncPropertyForm;
use Icinga\Module\Director\Forms\SyncRuleForm;
use Icinga\Module\Director\Forms\SyncRunForm;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Director\Objects\SyncRun;
use Icinga\Module\Director\Web\Table\SyncpropertyTable;
use Icinga\Module\Director\Web\Table\SyncRunTable;
use Icinga\Module\Director\Web\Tabs\SyncRuleTabs;
use Icinga\Module\Director\Web\Widget\SyncRunDetails;
use dipl\Html\Html;
use dipl\Html\Link;

class SyncruleController extends ActionController
{
    public function indexAction()
    {
        $this->setAutoRefreshInterval(10);
        $rule = $this->requireSyncRule();
        $this->tabs(new SyncRuleTabs($rule))->activate('show');
        $ruleName = $rule->get('rule_name');
        $this->addTitle($this->translate('Sync rule: %s'), $ruleName);

        $checkForm = SyncCheckForm::load()->setSyncRule($rule)->handleRequest();
        $runForm = SyncRunForm::load()->setSyncRule($rule)->handleRequest();

        if ($lastRunId = $rule->getLastSyncRunId()) {
            $run = SyncRun::load($lastRunId, $this->db());
        } else {
            $run = null;
        }

        $c = $this->content();
        $c->add(Html::p($rule->get('description')));
        if (! $rule->hasSyncProperties()) {
            $this->addPropertyHint($rule);
            return;
        }

        if (! $run) {
            $this->warning($this->translate('This Sync Rule has never been run before.'));
        }

        switch ($rule->get('sync_state')) {
            case 'unknown':
                $c->add(Html::p($this->translate(
                    "It's currently unknown whether we are in sync with this rule."
                    . ' You should either check for changes or trigger a new Sync Run.'
                )));
                break;
            case 'in-sync':
                $c->add(Html::p(sprintf(
                    $this->translate('This Sync Rule was last found to by in Sync at %s.'),
                    $rule->get('last_attempt')
                )));
                /*
                TODO: check whether...
                      - there have been imports since then, differing from former ones
                      - there have been activities since then
                */
                break;
            case 'pending-changes':
                $this->warning($this->translate(
                    'There are pending changes for this Sync Rule. You should trigger a new'
                    . ' Sync Run.'
                ));
                break;
            case 'failing':
                $this->error(sprintf(
                    $this->translate(
                        'This Sync Rule failed when last checked at %s: %s'
                    ),
                    $rule->get('last_attempt'),
                    $rule->get('last_error_message')
                ));
                break;
        }

        $c->add($checkForm);
        $c->add($runForm);

        if ($run) {
            $c->add(Html::h3($this->translate('Last sync run details')));
            $c->add(new SyncRunDetails($run));
            if ($run->get('rule_name') !== $ruleName) {
                $c->add(Html::p(sprintf(
                    $this->translate("It has been renamed since then, its former name was %s"),
                    $run->get('rule_name')
                )));
            }
        }
    }

    protected function addPropertyHint(SyncRule $rule)
    {
        $this->warning(Html::sprintf(
            $this->translate('You must define some %s before you can run this Sync Rule'),
            new Link(
                $this->translate('Sync Properties'),
                'director/syncrule/property',
                ['rule_id' => $rule->get('id')]
            )
        ));
    }

    protected function warning($msg)
    {
        $this->content()->add(Html::p(['class' => 'warning'], $msg));
    }

    protected function error($msg)
    {
        $this->content()->add(Html::p(['class' => 'error'], $msg));
    }

    public function addAction()
    {
        $this->editAction();
    }

    public function editAction()
    {
        $form = SyncRuleForm::load()
            ->setListUrl('director/syncrules')
            ->setDb($this->db());

        if ($id = $this->params->get('id')) {
            $form->loadObject($id);
            /** @var SyncRule $rule */
            $rule = $form->getObject();
            $this->tabs(new SyncRuleTabs($rule))->activate('edit');
            $this->addTitle(sprintf(
                $this->translate('Sync rule: %s'),
                $rule->rule_name
            ));

            if (! $rule->hasSyncProperties()) {
                $this->addPropertyHint($rule);
            }
        } else {
            $this->addTitle($this->translate('Add sync rule'));
            $this->tabs(new SyncRuleTabs())->activate('add');
        }

        $form->handleRequest();
        $this->content()->add($form);
    }

    public function propertyAction()
    {
        $rule = $this->requireSyncRule('rule_id');
        $this->tabs(new SyncRuleTabs($rule))->activate('property');

        $this->actions()->add(Link::create(
            $this->translate('Add sync property rule'),
            'director/syncrule/addproperty',
            ['rule_id' => $rule->get('id')],
            ['class' => 'icon-plus']
        ));
        $this->addTitle($this->translate('Sync properties') . ': ' . $rule->get('rule_name'));

        SyncpropertyTable::create($rule)
            ->handleSortPriorityActions($this->getRequest(), $this->getResponse())
            ->renderTo($this);
    }

    public function editpropertyAction()
    {
        $this->addpropertyAction();
    }

    public function addpropertyAction()
    {
        $db = $this->db();
        $rule = $this->requireSyncRule('rule_id');
        $ruleId = (int) $rule->get('id');

        $form = SyncPropertyForm::load()->setDb($db);
        if ($id = $this->params->get('id')) {
            $form->loadObject($id);
            $this->addTitle(
                $this->translate('Sync "%s": %s'),
                $form->getObject()->get('destination_field'),
                $rule->get('rule_name')
            );
        } else {
            $this->addTitle(
                $this->translate('Add sync property: %s'),
                $rule->get('rule_name')
            );
        }
        $form->setRule($rule);
        $form->setSuccessUrl('director/syncrule/property', ['rule_id' => $ruleId]);

        $this->actions()->add(new Link(
            $this->translate('back'),
            'director/syncrule/property',
            ['rule_id' => $ruleId],
            ['class' => 'icon-left-big']
        ));

        $this->content()->add($form->handleRequest());
        $this->tabs(new SyncRuleTabs($rule))->activate('property');
        SyncpropertyTable::create($rule)
            ->handleSortPriorityActions($this->getRequest(), $this->getResponse())
            ->renderTo($this);
    }

    public function historyAction()
    {
        $this->setAutoRefreshInterval(30);
        $rule = $this->requireSyncRule();
        $this->tabs(new SyncRuleTabs($rule))->activate('history');
        $this->addTitle($this->translate('Sync history') . ': ' . $rule->rule_name);

        if ($runId = $this->params->get('run_id')) {
            $run = SyncRun::load($runId, $this->db());
            $this->content()->add(new SyncRunDetails($run));
        }
        SyncRunTable::create($rule)->renderTo($this);
    }

    protected function requireSyncRule($key = 'id')
    {
        $id = $this->params->get($key);
        return SyncRule::load($id, $this->db());
    }
}
