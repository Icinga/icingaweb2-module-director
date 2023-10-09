<?php

namespace Icinga\Module\Director\DirectorObject\Automation;

use Icinga\Module\Director\Data\Exporter;
use Icinga\Module\Director\Data\ObjectImporter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Objects\DirectorJob;
use Icinga\Module\Director\Objects\IcingaHostGroup;
use Icinga\Module\Director\Objects\IcingaServiceGroup;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Objects\IcingaTemplateChoiceHost;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\SyncRule;

class ImportExport
{
    /** @var Db */
    protected $connection;

    /** @var Exporter */
    protected $exporter;

    public function __construct(Db $connection)
    {
        $this->connection = $connection;
        $this->exporter = new Exporter($connection);
    }

    public function serializeAllServiceSets()
    {
        $res = [];
        foreach (IcingaServiceSet::loadAll($this->connection) as $object) {
            if ($object->get('host_id')) {
                continue;
            }
            $res[] = $this->exporter->export($object);
        }

        return $res;
    }

    public function serializeAllHostTemplateChoices()
    {
        $res = [];
        foreach (IcingaTemplateChoiceHost::loadAll($this->connection) as $object) {
            $res[] = $this->exporter->export($object);
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
            $res[] = $this->exporter->export($object);
        }

        return $res;
    }

    public function serializeAllDataLists()
    {
        $res = [];
        foreach (DirectorDatalist::loadAll($this->connection) as $object) {
            $res[] = $this->exporter->export($object);
        }

        return $res;
    }

    public function serializeAllJobs()
    {
        $res = [];
        foreach (DirectorJob::loadAll($this->connection) as $object) {
            $res[] = $this->exporter->export($object);
        }

        return $res;
    }

    public function serializeAllImportSources()
    {
        $res = [];
        foreach (ImportSource::loadAll($this->connection) as $object) {
            $res[] = $this->exporter->export($object);
        }

        return $res;
    }

    public function serializeAllSyncRules()
    {
        $res = [];
        foreach (SyncRule::loadAll($this->connection) as $object) {
            $res[] = $this->exporter->export($object);
        }

        return $res;
    }

    public function unserializeImportSources($objects)
    {
        $count = 0;
        $this->connection->runFailSafeTransaction(function () use ($objects, &$count) {
            $importer = new ObjectImporter($this->connection);
            foreach ($objects as $object) {
                $importer->import(ImportSource::class, $object)->store();
                $count++;
            }
        });

        return $count;
    }

    public function unserializeSyncRules($objects)
    {
        $count = 0;
        $this->connection->runFailSafeTransaction(function () use ($objects, &$count) {
            $importer = new ObjectImporter($this->connection);
            foreach ($objects as $object) {
                $importer->import(SyncRule::class, $object)->store();
            }
            $count++;
        });

        return $count;
    }
}
