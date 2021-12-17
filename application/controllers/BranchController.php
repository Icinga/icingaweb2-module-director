<?php

namespace Icinga\Module\Director\Controllers;

use gipfl\Diff\HtmlRenderer\SideBySideDiff;
use gipfl\Diff\PhpDiff;
use gipfl\IcingaWeb2\Widget\NameValueTable;
use Icinga\Module\Director\Data\Db\DbObjectStore;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db\Branch\BranchActivity;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\IcingaObject;
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
    }

    protected function checkDirectorPermissions()
    {
    }

    public function activityAction()
    {
        $this->assertPermission('director/showconfig');
        $ts = $this->params->getRequired('ts');
        $this->addSingleTab($this->translate('Activity'));
        $this->addTitle($this->translate('Branch Activity'));
        $activity = BranchActivity::load($ts, $this->db());
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
        foreach ($activity->getFormerProperties()->jsonSerialize() as $key => $value) {
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
        foreach ($activity->getModifiedProperties()->jsonSerialize() as $key => $value) {
            $object->set($key, $value);
        }

        return $object;
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
