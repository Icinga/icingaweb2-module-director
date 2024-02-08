<?php

namespace Icinga\Module\Director\Controllers;

use gipfl\Diff\HtmlRenderer\SideBySideDiff;
use gipfl\Diff\PhpDiff;
use gipfl\IcingaWeb2\Widget\NameValueTable;
use Icinga\Module\Director\Data\Db\DbObjectStore;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db\Branch\BranchActivity;
use Icinga\Module\Director\Db\Branch\BranchStore;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Director\PlainObjectRenderer;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Controller\BranchHelper;
use Icinga\Module\Director\Web\Widget\IcingaConfigDiff;
use ipl\Html\Html;

class BranchController extends ActionController
{
    use BranchHelper;

    public function init()
    {
        parent::init();
        IcingaObject::setDbObjectStore(new DbObjectStore($this->db(), $this->getBranch()));
        SyncRule::setDbObjectStore(new DbObjectStore($this->db(), $this->getBranch()));
    }

    protected function checkDirectorPermissions()
    {
    }

    public function activityAction()
    {
        $this->assertPermission('director/showconfig');
        $ts = $this->params->getRequired('ts');
        $activity = BranchActivity::load($ts, $this->db());
        $store = new BranchStore($this->db());
        $branch = $store->fetchBranchByUuid($activity->getBranchUuid());
        if ($branch->isSyncPreview()) {
            $this->addSingleTab($this->translate('Sync Preview'));
            $this->addTitle($this->translate('Expected Modification'));
        } else {
            $this->addSingleTab($this->translate('Activity'));
            $this->addTitle($this->translate('Branch Activity'));
        }

        $this->content()->add($this->prepareActivityInfo($activity));
        $this->showActivity($activity);
    }

    protected function prepareActivityInfo(BranchActivity $activity)
    {
        $table = new NameValueTable();
        $table->addNameValuePairs([
            $this->translate('Author') => $activity->getAuthor(),
            $this->translate('Date') => date('Y-m-d H:i:s', $activity->getTimestamp()),
            $this->translate('Action') => $activity->getAction()
                . ' ' . preg_replace('/^icinga_/', '', $activity->getObjectTable())
                . ' ' . $activity->getObjectName(),
            // $this->translate('Actions') => ['Undo form'],
        ]);
        return $table;
    }

    protected function leftFromActivity(BranchActivity $activity)
    {
        if ($activity->isActionCreate()) {
            return null;
        }
        $object = DbObjectTypeRegistry::newObject($activity->getObjectTable(), [], $this->db());
        $properties = $this->objectTypeFirst($activity->getFormerProperties()->jsonSerialize());
        foreach ($properties as $key => $value) {
            $object->set($key, $value);
        }

        return $object;
    }

    protected function rightFromActivity(BranchActivity $activity)
    {
        if ($activity->isActionDelete()) {
            return null;
        }
        $object = DbObjectTypeRegistry::newObject($activity->getObjectTable(), [], $this->db());
        if (! $activity->isActionCreate()) {
            foreach ($activity->getFormerProperties()->jsonSerialize() as $key => $value) {
                $object->set($key, $value);
            }
        }
        $properties = $this->objectTypeFirst($activity->getModifiedProperties()->jsonSerialize());
        foreach ($properties as $key => $value) {
            $object->set($key, $value);
        }

        return $object;
    }

    protected function objectTypeFirst($properties)
    {
        $properties = (array) $properties;
        if (isset($properties['object_type'])) {
            $type = $properties['object_type'];
            unset($properties['object_type']);
            $properties = ['object_type' => $type] + $properties;
        }

        return $properties;
    }

    protected function showActivity(BranchActivity $activity)
    {
        $left = $this->leftFromActivity($activity);
        $right = $this->rightFromActivity($activity);
        if ($left instanceof IcingaObject || $right instanceof IcingaObject) {
            $this->content()->add(new IcingaConfigDiff(
                $left ? $left->toSingleIcingaConfig() : $this->createEmptyConfig(),
                $right ? $right->toSingleIcingaConfig() : $this->createEmptyConfig()
            ));
        } else {
            $this->content()->add([
                Html::tag('h3', $this->translate('Modification')),
                new SideBySideDiff(new PhpDiff(
                    PlainObjectRenderer::render($left->getProperties()),
                    PlainObjectRenderer::render($right->getProperties())
                ))
            ]);
        }
    }

    protected function createEmptyConfig()
    {
        return new IcingaConfig($this->db());
    }
}
