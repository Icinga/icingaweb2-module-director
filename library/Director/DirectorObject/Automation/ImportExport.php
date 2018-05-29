<?php

namespace Icinga\Module\Director\DirectorObject\Automation;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Objects\DirectorJob;
use Icinga\Module\Director\Objects\IcingaHostGroup;
use Icinga\Module\Director\Objects\IcingaServiceGroup;
use Icinga\Module\Director\Objects\IcingaTemplateChoiceHost;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\SyncRule;

class ImportExport
{
    protected $connection;

    public function __construct(Db $connection)
    {
        $this->connection = $connection;
    }

    public function serializeAllHostTemplateChoices()
    {
        $res = [];
        foreach (IcingaTemplateChoiceHost::loadAll($this->connection) as $object) {
            $res[] = $object->export();
        }

        return $res;
    }

    public function serializeAllHostGroups()
    {
        $res = [];
        foreach (IcingaHostGroup::loadAll($this->connection) as $object) {
            $res[] = $object->toPlainObject();
        }

        return $res;
    }

    public function serializeAllServiceGroups()
    {
        $res = [];
        foreach (IcingaServiceGroup::loadAll($this->connection) as $object) {
            $res[] = $object->toPlainObject();
        }

        return $res;
    }

    public function serializeAllDataFields()
    {
        $res = [];
        foreach (DirectorDatafield::loadAll($this->connection) as $object) {
            $res[] = $object->export();
        }

        return $res;
    }

    public function serializeAllDataLists()
    {
        $res = [];
        foreach (DirectorDatalist::loadAll($this->connection) as $object) {
            $res[] = $object->export();
        }

        return $res;
    }

    public function serializeAllJobs()
    {
        $res = [];
        foreach (DirectorJob::loadAll($this->connection) as $object) {
            $res[] = $object->export();
        }

        return $res;
    }

    public function serializeAllImportSources()
    {
        $res = [];
        foreach (ImportSource::loadAll($this->connection) as $object) {
            $res[] = $object->export();
        }

        return $res;
    }

    public function serializeAllSyncRules()
    {
        $res = [];
        foreach (SyncRule::loadAll($this->connection) as $object) {
            $res[] = $object->export();
        }

        return $res;
    }
}
