<?php

namespace Icinga\Module\Director\Test;

use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\Import\Sync;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\SyncProperty;
use Icinga\Module\Director\Objects\SyncRule;

abstract class SyncTest extends BaseTestCase
{
    protected $objectType;
    
    protected $keyColumn;
    
    /** @var  ImportSource */
    protected $source;

    /** @var  SyncRule */
    protected $rule;

    /** @var  SyncProperty[] */
    protected $properties = array();

    /** @var  Sync */
    protected $sync;

    public function setUp(): void
    {
        $this->source = ImportSource::create(array(
            'source_name'    => 'testimport',
            'provider_class' => 'Icinga\\Module\\Director\\Test\\ImportSourceDummy',
            'key_column'     => $this->keyColumn,
        ));
        $this->source->store($this->getDb());

        $this->rule = SyncRule::create(array(
            'rule_name'      => 'testrule',
            'object_type'    => $this->objectType,
            'update_policy'  => 'merge',
            'purge_existing' => 'n'
        ));
        $this->rule->store($this->getDb());

        $this->sync = new Sync($this->rule);
    }

    public function tearDown(): void
    {
        // properties should be deleted automatically
        if ($this->rule !== null && $this->rule->hasBeenLoadedFromDb()) {
            $this->rule->delete();
        }

        if ($this->source !== null && $this->source->hasBeenLoadedFromDb()) {
            $this->source->delete();
        }

        // find objects created by this class and delete them
        $db = $this->getDb();
        $dummy = IcingaObject::createByType($this->objectType, array(), $db);
        $query = $db->getDbAdapter()->select()
            ->from($dummy->getTableName())
            ->where('object_name LIKE ?', 'SYNCTEST_%');

        /** @var IcingaObject $object */
        foreach (IcingaObject::loadAllByType($this->objectType, $db, $query) as $object) {
            $object->delete();
        }

        // make sure cache is clean for other tests
        PrefetchCache::forget();
        DbObject::clearAllPrefetchCaches();
    }

    /**
     * @param array $rows
     *
     * @throws IcingaException
     */
    protected function runImport($rows)
    {
        ImportSourceDummy::setRows($rows);
        $this->source->runImport();
        if ($this->source->get('import_state') !== 'in-sync') {
            throw new IcingaException('Import failed: %s', $this->source->get('last_error_message'));
        }
    }

    protected function setUpProperty($properties = array())
    {
        $properties = array_merge(array(
            'rule_id'      => $this->rule->id,
            'source_id'    => $this->source->id,
            'merge_policy' => 'override',
        ), $properties);

        $this->properties[] = $property = SyncProperty::create($properties);
        $property->store($this->getDb());
    }
}
