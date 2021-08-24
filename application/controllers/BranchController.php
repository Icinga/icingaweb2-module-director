<?php

namespace Icinga\Module\Director\Controllers;

use gipfl\Diff\HtmlRenderer\SideBySideDiff;
use gipfl\Diff\PhpDiff;
use gipfl\IcingaWeb2\Widget\NameValueTable;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Data\Json;
use Icinga\Module\Director\Db\Branch\IcingaObjectModification;
use Icinga\Module\Director\Db\Branch\ObjectModification;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Controller\BranchHelper;
use ipl\Html\Html;
use ipl\Html\ValidHtml;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class BranchController extends ActionController
{
    use BranchHelper;

    protected function checkDirectorPermissions()
    {
    }

    public function activityAction()
    {
        $this->assertPermission('director/showconfig');
        $uuid = Uuid::fromString($this->params->getRequired('uuid'));
        $this->addSingleTab($this->translate('Activity'));
        $this->addTitle($this->translate('Branch Activity'));
        $activity = $this->loadBranchActivity($uuid);
        $this->content()->add($this->prepareActivityInfo($activity));
        $this->showActivity($activity);
    }

    protected function prepareActivityInfo($activity)
    {
        $modification = ObjectModification::fromSerialization(json_decode($activity->change_set));
        /** @var IcingaObject $class IDE hint, it's a string */
        $class = $modification->getClassName();
        $keyParams = (array) $modification->getKeyParams(); // TODO: verify type
        $dummy = $class::create($keyParams);

        $table = new NameValueTable();
        $table->addNameValuePairs([
            $this->translate('Author') => 'branch owner',
            $this->translate('Date') => date('Y-m-d H:i:s', $activity->change_time / 1000),
            $this->translate('Action') => $modification->getAction()
                . ' ' . $dummy->getShortTableName()
                . ' ' . $dummy->getObjectName(),
            $this->translate('Change UUID') => Uuid::fromBytes($activity->uuid)->toString(),
            // $this->translate('Actions') => ['Undo form'],
        ]);
        return $table;
    }

    protected function showActivity($activity)
    {
        $modification = ObjectModification::fromSerialization(Json::decode($activity->change_set));
        /** @var string|DbObject $class */
        $class = $modification->getClassName();

        // TODO: Show JSON-Diff for non-IcingaObject's
        $keyParams = (array) $modification->getKeyParams(); // TODO: verify type
        $dummy = $class::create($keyParams, $this->db());
        if ($modification->isCreation()) {
            $left = $this->createEmptyConfig();
            $right = $dummy->setProperties(
                (array) $modification->getProperties()->jsonSerialize()
            )->toSingleIcingaConfig();
        } elseif ($modification->isDeletion()) {
            $left = $class::load($keyParams, $this->db())->toSingleIcingaConfig();
            $right = $this->createEmptyConfig();
        } else {
            // TODO: highlight properties that have been changed in the meantime
            // TODO: Deal with missing $existing
            $existing = $class::load($keyParams, $this->db());
            $left = $existing->toSingleIcingaConfig();
            $right = clone($existing);
            IcingaObjectModification::applyModification($modification, $right, $this->db());
            $right = $right->toSingleIcingaConfig();
        }
        $changes = $this->getConfigDiffs($left, $right);
        foreach ($changes as $filename => $diff) {
            $this->content()->add([
                Html::tag('h3', $filename),
                $diff
            ]);
        }
    }

    protected function createEmptyConfig()
    {
        return new IcingaConfig($this->db());
    }

    /**
     * @param IcingaConfig $oldConfig
     * @param IcingaConfig $newConfig
     * @return ValidHtml[]
     */
    protected function getConfigDiffs(IcingaConfig $oldConfig, IcingaConfig $newConfig)
    {
        $oldFileNames = $oldConfig->getFileNames();
        $newFileNames = $newConfig->getFileNames();

        $fileNames = array_merge($oldFileNames, $newFileNames);

        $diffs = [];
        foreach ($fileNames as $filename) {
            if (in_array($filename, $oldFileNames)) {
                $left = $oldConfig->getFile($filename)->getContent();
            } else {
                $left = '';
            }

            if (in_array($filename, $newFileNames)) {
                $right = $newConfig->getFile($filename)->getContent();
            } else {
                $right = '';
            }
            if ($left === $right) {
                continue;
            }

            $diffs[$filename] = new SideBySideDiff(new PhpDiff($left, $right));
        }

        return $diffs;
    }

    protected function loadBranchActivity(UuidInterface $uuid)
    {
        $db = $this->db()->getDbAdapter();
        return $db->fetchRow(
            $db->select()->from('director_branch_activity')->where('uuid = ?', $uuid->getBytes())
        );
    }
}
