<?php

namespace Icinga\Module\Director\Controllers;

use gipfl\IcingaWeb2\Link;
use gipfl\Web\Widget\Hint;
use Icinga\Date\DateFormatter;
use Icinga\Module\Director\Data\Db\DbObjectStore;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\Branch\BranchStore;
use Icinga\Module\Director\Db\Branch\BranchSupport;
use Icinga\Module\Director\Web\Controller\BranchHelper;
use Icinga\Module\Director\Web\Form\ClickHereForm;
use Icinga\Module\Director\Web\Table\BranchActivityTable;
use Icinga\Module\Director\Web\Widget\IcingaConfigDiff;
use Icinga\Module\Director\Web\Widget\UnorderedList;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Forms\SyncCheckForm;
use Icinga\Module\Director\Forms\SyncPropertyForm;
use Icinga\Module\Director\Forms\SyncRuleForm;
use Icinga\Module\Director\Forms\SyncRunForm;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Import\Sync;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Web\ActionBar\AutomationObjectActionBar;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Director\Objects\SyncRun;
use Icinga\Module\Director\Web\Form\CloneSyncRuleForm;
use Icinga\Module\Director\Web\Table\SyncpropertyTable;
use Icinga\Module\Director\Web\Table\SyncRunTable;
use Icinga\Module\Director\Web\Tabs\SyncRuleTabs;
use Icinga\Module\Director\Web\Widget\SyncRunDetails;
use Icinga\Web\Notification;
use ipl\Html\Form;
use ipl\Html\Html;

class SyncruleController extends ActionController
{
    use BranchHelper;

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function indexAction()
    {
        $this->setAutorefreshInterval(10);
        $rule = $this->requireSyncRule();
        $this->tabs(new SyncRuleTabs($rule))->activate('show');
        $ruleName = $rule->get('rule_name');
        $this->addTitle($this->translate('Sync rule: %s'), $ruleName);

        $checkForm = SyncCheckForm::load()->setSyncRule($rule)->handleRequest();
        $store = new DbObjectStore($this->db(), $this->getBranch());
        $runForm = new SyncRunForm($rule, $store);
        $runForm->on(SyncRunForm::ON_SUCCESS, function (SyncRunForm $form) {
            $message = $form->getSuccessMessage();
            if ($message === null) {
                Notification::error($this->translate('Synchronization failed'));
            } else {
                Notification::success($message);
            }
            $this->redirectNow($this->url());
        });
        $runForm->handleRequest($this->getServerRequest());

        if ($lastRunId = $rule->getLastSyncRunId()) {
            $run = SyncRun::load($lastRunId, $this->db());
        } else {
            $run = null;
        }

        $c = $this->content();
        $c->add(Html::tag('p', null, $rule->get('description')));
        if (! $rule->hasSyncProperties()) {
            $this->addPropertyHint($rule);
            return;
        }
        $this->addMainActions();
        if (! $run) {
            $c->add(Hint::warning($this->translate('This Sync Rule has never been run before.')));
        }

        switch ($rule->get('sync_state')) {
            case 'unknown':
                $c->add(Html::tag('p', null, $this->translate(
                    "It's currently unknown whether we are in sync with this rule."
                    . ' You should either check for changes or trigger a new Sync Run.'
                )));
                break;
            case 'in-sync':
                $c->add(Html::tag('p', null, sprintf(
                    $this->translate('This Sync Rule was last found to be in Sync at %s.'),
                    $rule->get('last_attempt')
                )));
                /*
                TODO: check whether...
                      - there have been imports since then, differing from former ones
                      - there have been activities since then
                */
                break;
            case 'pending-changes':
                $c->add(Hint::warning($this->translate(
                    'There are pending changes for this Sync Rule. You should trigger a new'
                    . ' Sync Run.'
                )));
                break;
            case 'failing':
                $c->add(Hint::error(sprintf(
                    $this->translate(
                        'This Sync Rule failed when last checked at %s: %s'
                    ),
                    $rule->get('last_attempt'),
                    $rule->get('last_error_message')
                )));
                break;
        }

        $c->add($checkForm);
        if ($this->hasBranch()) {
            $objectType = $rule->get('object_type');
            $table = DbObjectTypeRegistry::tableNameByType($objectType);
            if (! BranchSupport::existsForTableName($table)) {
                $this->showNotInBranch(sprintf($this->translate("Synchronizing '%s'"), $objectType));
                return;
            }
        }

        $c->add($runForm);

        if ($run) {
            $c->add(Html::tag('h3', null, $this->translate('Last sync run details')));
            $c->add(new SyncRunDetails($run));
            if ($run->get('rule_name') !== $ruleName) {
                $c->add(Html::tag('p', null, sprintf(
                    $this->translate("It has been renamed since then, its former name was %s"),
                    $run->get('rule_name')
                )));
            }
        }
    }

    /**
     * @param SyncRule $rule
     */
    protected function addPropertyHint(SyncRule $rule)
    {
        $this->content()->add(Hint::warning(Html::sprintf(
            $this->translate('You must define some %s before you can run this Sync Rule'),
            new Link(
                $this->translate('Sync Properties'),
                'director/syncrule/property',
                ['rule_id' => $rule->get('id')]
            )
        )));
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function addAction()
    {
        $this->editAction();
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Exception
     */
    public function previewAction()
    {
        $rule = $this->requireSyncRule();
        $branchSupport = BranchSupport::existsForSyncRule($rule);
        $branchStore = new BranchStore($this->db());
        $owner = $this->getAuth()->getUser()->getUsername();
        if ($branchSupport) {
            if ($this->getBranch()->isBranch()) {
                $tmpBranchName = sprintf(
                    '%s/%s-%s',
                    Branch::PREFIX_SYNC_PREVIEW,
                    $this->getBranch()->getUuid()->toString(),
                    $rule->get('id')
                );
                // We could keep changes for preview on branch too
                $branchStore->deleteByName($tmpBranchName);
                $tmpBranch = $branchStore->cloneBranchForSync($this->getBranch(), $tmpBranchName, $owner);
                $after = 1600000000; // a date in 2020, minus 10000000
            } else {
                $tmpBranchName = Branch::PREFIX_SYNC_PREVIEW . '/' . $rule->get('id');
                $tmpBranch = $branchStore->fetchOrCreateByName($tmpBranchName, $owner);
                $after = null;
            }
            $store = new DbObjectStore($this->db(), $tmpBranch);
        } else {
            $tmpBranch = $store = null;
        }

        $this->tabs(new SyncRuleTabs($rule))->activate('preview');
        $this->addTitle($this->translate('Sync Preview'));
        $sync = new Sync($rule, $store);
        $keepBranchPreview = false;
        if ($tmpBranch) {
            if ($lastTime = $branchStore->getLastActivityTime($tmpBranch, $after)) {
                if ((time() - $lastTime) > 100) {
                    $branchStore->wipeBranch($tmpBranch, $after);
                } else {
                    $here = (new ClickHereForm())->handleRequest($this->getServerRequest());
                    if ($here->hasBeenClicked()) {
                        $branchStore->wipeBranch($tmpBranch, $after);
                        $this->redirectNow($this->url());
                    } else {
                        $keepBranchPreview = true;
                    }
                    $this->content()->add(Hint::info(Html::sprintf(
                        $this->translate('This preview has been generated %s, please click %s to regenerate it'),
                        DateFormatter::timeAgo($lastTime),
                        $here
                    )));
                }
            }
        }
        if (!$keepBranchPreview) {
            $modifications = $sync->getExpectedModifications();
        }

        if ($tmpBranch) {
            try {
                if (!$keepBranchPreview) {
                    $sync->apply();
                }
            } catch (\Exception $e) {
                $this->content()->add(Hint::error($e->getMessage()));
                return;
            }

            $changes = new BranchActivityTable($tmpBranch->getUuid(), $this->db());
            $changes->disableObjectLink();
            if (count($changes) === 0) {
                $this->showInSync();
            }
            $changes->renderTo($this);
        } else {
            if (empty($modifications)) {
                $this->showInSync();
                return;
            }
            $this->showExpectedModificationSummary($modifications);
        }
    }

    protected function showInSync()
    {
        $this->content()->add(Hint::ok($this->translate(
            'This Sync Rule is in sync and would currently not apply any changes'
        )));
    }

    protected function showExpectedModificationSummary($modifications)
    {
        $create = [];
        $modify = [];
        $delete = [];
        $modifiedProperties = [];
        /** @var IcingaObject $object */
        foreach ($modifications as $object) {
            if ($object->hasBeenLoadedFromDb()) {
                if ($object->shouldBeRemoved()) {
                    $delete[] = $object;
                } else {
                    $modify[] = $object;
                    foreach ($object->getModifiedProperties() as $property => $value) {
                        if (isset($modifiedProperties[$property])) {
                            $modifiedProperties[$property]++;
                        } else {
                            $modifiedProperties[$property] = 1;
                        }
                    }
                    if (! $object instanceof IcingaObject) {
                        continue;
                    }
                    if ($object->supportsGroups()) {
                        if ($object->hasModifiedGroups()) {
                            if (isset($modifiedProperties['groups'])) {
                                $modifiedProperties['groups']++;
                            } else {
                                $modifiedProperties['groups'] = 1;
                            }
                        }
                    }

                    if ($object->supportsImports()) {
                        if ($object->imports()->hasBeenModified()) {
                            if (isset($modifiedProperties['imports'])) {
                                $modifiedProperties['imports']++;
                            } else {
                                $modifiedProperties['imports'] = 1;
                            }
                        }
                    }
                    if ($object->supportsCustomVars()) {
                        if ($object->vars()->hasBeenModified()) {
                            foreach ($object->vars() as $var) {
                                if ($var->isNew()) {
                                    $varName = 'add vars.' . $var->getKey();
                                } elseif ($var->hasBeenDeleted()) {
                                    $varName = 'remove vars.' . $var->getKey();
                                } elseif ($var->hasBeenModified()) {
                                    $varName = 'vars.' . $var->getKey();
                                } else {
                                    continue;
                                }
                                if (isset($modifiedProperties[$varName])) {
                                    $modifiedProperties[$varName]++;
                                } else {
                                    $modifiedProperties[$varName] = 1;
                                }
                            }
                        }
                    }
                }
            } else {
                $create[] = $object;
            }
        }

        $content = $this->content();
        if (! empty($delete)) {
            $content->add([
                Html::tag('h2', ['class' => 'icon-cancel action-delete'], sprintf(
                    $this->translate('%d object(s) will be deleted'),
                    count($delete)
                )),
                $this->objectList($delete)
            ]);
        }
        if (! empty($modify)) {
            $content->add([
                Html::tag('h2', ['class' => 'icon-wrench action-modify'], sprintf(
                    $this->translate('%d object(s) will be modified'),
                    count($modify)
                )),
                $this->listModifiedProperties($modifiedProperties),
                $this->objectList($modify),
            ]);
        }
        if (! empty($create)) {
            $content->add([
                Html::tag('h2', ['class' => 'icon-plus action-create'], sprintf(
                    $this->translate('%d object(s) will be created'),
                    count($create)
                )),
                $this->objectList($create)
            ]);
        }
    }

    /**
     * @param IcingaObject[] $objects
     * @return \ipl\Html\HtmlElement
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function objectList($objects)
    {
        return Html::tag('p', $this->firstNames($objects));
    }

    /**
     * Lots of duplicated code, this whole diff logic should be mouved to a
     * dedicated class
     *
     * @param IcingaObject[] $objects
     * @param int $max
     * @return string
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function firstNames($objects, $max = 50)
    {
        $names = [];
        $list = new UnorderedList([], ['class' => 'unordred-list']);
        $total = count($objects);
        $i = 0;
        PrefetchCache::forget();
        IcingaHost::clearAllPrefetchCaches(); // why??
        IcingaService::clearAllPrefetchCaches();
        foreach ($objects as $object) {
            $i++;
            $name = $this->getObjectNameString($object);
            if ($object->hasBeenLoadedFromDb()) {
                if ($object instanceof IcingaHost) {
                    $names[$name] = Link::create(
                        $name,
                        'director/host',
                        ['name' => $name],
                        ['data-base-target' => '_next']
                    );
                    $oldObject = IcingaHost::load($object->getObjectName(), $this->db());
                    $cfgNew = new IcingaConfig($this->db());
                    $cfgOld = new IcingaConfig($this->db());
                    $oldObject->renderToConfig($cfgOld);
                    $object->renderToConfig($cfgNew);
                    foreach (IcingaConfigDiff::getDiffs($cfgOld, $cfgNew) as $file => $diff) {
                        $names[$name . '___PRETITLE___' . $file] = Html::tag('h3', $file);
                        $names[$name . '___PREVIEW___' . $file] = $diff;
                    }
                } elseif ($object instanceof IcingaService && $object->isObject()) {
                    $host = $object->getRelated('host');

                    $names[$name] = Link::create(
                        $name,
                        'director/service/edit',
                        [
                            'name' => $object->getObjectName(),
                            'host' => $host->getObjectName()
                        ],
                        ['data-base-target' => '_next']
                    );
                    $oldObject = IcingaService::load([
                        'host_id' => $host->get('id'),
                        'object_name' => $object->getObjectName()
                    ], $this->db());

                    $cfgNew = new IcingaConfig($this->db());
                    $cfgOld = new IcingaConfig($this->db());
                    $oldObject->renderToConfig($cfgOld);
                    $object->renderToConfig($cfgNew);
                    foreach (IcingaConfigDiff::getDiffs($cfgOld, $cfgNew) as $file => $diff) {
                        $names[$name . '___PRETITLE___' . $file] = Html::tag('h3', $file);
                        $names[$name . '___PREVIEW___' . $file] = $diff;
                    }
                } else {
                    $names[$name] = $name;
                }
            } else {
                $names[$name] = $name;
            }
            if ($i === $max) {
                break;
            }
        }
        ksort($names);

        foreach ($names as $name) {
            $list->addItem($name);
        }

        if ($total > $max) {
            $list->add(sprintf(
                $this->translate('...and %d more'),
                $total - $max
            ));
        }

        return $list;
    }

    protected function listModifiedProperties($properties)
    {
        $list = new UnorderedList();
        foreach ($properties as $property => $cnt) {
            $list->addItem("{$cnt}x $property");
        }

        return $list;
    }

    protected function getObjectNameString($object)
    {
        if ($object instanceof IcingaService) {
            if ($object->isObject()) {
                return $object->getRelated('host')->getObjectName()
                    . ': ' . $object->getObjectName();
            } else {
                return $object->getObjectName();
            }
        } elseif ($object instanceof IcingaHost) {
            return $object->getObjectName();
        } elseif ($object instanceof ExportInterface) {
            return $object->getUniqueIdentifier();
        } elseif ($object instanceof IcingaObject) {
            return $object->getObjectName();
        } else {
            /** @var \Icinga\Module\Director\Data\Db\DbObject $object */
            return json_encode($object->getKeyParams());
        }
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function editAction()
    {
        $form = SyncRuleForm::load()
            ->setListUrl('director/syncrules')
            ->setDb($this->db());

        if ($id = $this->params->get('id')) {
            $form->loadObject((int) $id);
            /** @var SyncRule $rule */
            $rule = $form->getObject();
            $this->tabs(new SyncRuleTabs($rule))->activate('edit');
            $this->addTitle(sprintf(
                $this->translate('Sync rule: %s'),
                $rule->get('rule_name')
            ));
            $this->addMainActions();

            if (! $rule->hasSyncProperties()) {
                $this->addPropertyHint($rule);
            }
            if ($this->showNotInBranch($this->translate('Modifying Sync Rules'))) {
                return;
            }
        } else {
            $this->addTitle($this->translate('Add sync rule'));
            $this->tabs(new SyncRuleTabs())->activate('add');
            if ($this->showNotInBranch($this->translate('Creating Sync Rules'))) {
                return;
            }
        }

        $form->handleRequest();
        $this->content()->add($form);
    }

    /**
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function cloneAction()
    {
        $id = $this->params->getRequired('id');
        $rule = SyncRule::loadWithAutoIncId((int) $id, $this->db());
        $this->tabs()->add('show', [
            'url'       => 'director/syncrule',
            'urlParams' => ['id' => $id],
            'label'     => $this->translate('Sync rule'),
        ])->add('clone', [
            'url'       => 'director/syncrule/clone',
            'urlParams' => ['id' => $id],
            'label'     => $this->translate('Clone'),
        ])->activate('clone');
        $this->addTitle('Clone: %s', $rule->get('rule_name'));
        $this->actions()->add(
            Link::create(
                $this->translate('Modify'),
                'director/syncrule/edit',
                ['id' => $rule->get('id')],
                ['class' => 'icon-paste']
            )
        );
        if ($this->showNotInBranch($this->translate('Cloning Sync Rules'))) {
            return;
        }

        $form = new CloneSyncRuleForm($rule);
        $this->content()->add($form);
        $form->on(Form::ON_SUCCESS, function (CloneSyncRuleForm $form) {
            $this->getResponse()->redirectAndExit($form->getSuccessUrl());
        });
        $form->handleRequest($this->getServerRequest());
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
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

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function editpropertyAction()
    {
        $this->addpropertyAction();
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function addpropertyAction()
    {
        $db = $this->db();
        $rule = $this->requireSyncRule('rule_id');
        $ruleId = (int) $rule->get('id');

        $form = SyncPropertyForm::load()->setDb($db);
        $this->tabs(new SyncRuleTabs($rule))->activate('property');
            $this->actions()->add(new Link(
                $this->translate('back'),
                'director/syncrule/property',
                ['rule_id' => $ruleId],
                ['class' => 'icon-left-big']
            ));
        if ($id = $this->params->get('id')) {
            $form->loadObject((int) $id);
            $this->addTitle(
                $this->translate('Sync "%s": %s'),
                $form->getObject()->get('destination_field'),
                $rule->get('rule_name')
            );
            if ($this->showNotInBranch($this->translate('Modifying Sync Rules'))) {
                return;
            }
        } else {
            $this->addTitle(
                $this->translate('Add sync property: %s'),
                $rule->get('rule_name')
            );
            if ($this->showNotInBranch($this->translate('Modifying Sync Rules'))) {
                return;
            }
        }
        $form->setRule($rule);
        $form->setSuccessUrl('director/syncrule/property', ['rule_id' => $ruleId]);
        $this->content()->add($form->handleRequest());
        SyncpropertyTable::create($rule)
            ->handleSortPriorityActions($this->getRequest(), $this->getResponse())
            ->renderTo($this);
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function historyAction()
    {
        $this->setAutorefreshInterval(30);
        $rule = $this->requireSyncRule();
        $this->tabs(new SyncRuleTabs($rule))->activate('history');
        $this->addTitle($this->translate('Sync history') . ': ' . $rule->get('rule_name'));

        if ($runId = $this->params->get('run_id')) {
            $run = SyncRun::load($runId, $this->db());
            $this->content()->add(new SyncRunDetails($run));
        }
        (new SyncRunTable($rule))->renderTo($this);
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function addMainActions()
    {
        $this->actions(new AutomationObjectActionBar(
            $this->getRequest()
        ));
        $source = $this->requireSyncRule();

        $this->actions()->add(Link::create(
            $this->translate('Add to Basket'),
            'director/basket/add',
            [
                'type'  => 'SyncRule',
                'names' => $source->getUniqueIdentifier()
            ],
            [
                'class' => 'icon-tag',
                'data-base-target' => '_next',
            ]
        ));
    }

    /**
     * @param string $key
     * @return SyncRule
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function requireSyncRule($key = 'id')
    {
        $id = $this->params->get($key);
        return SyncRule::loadWithAutoIncId($id, $this->db());
    }
}
