<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Application\Benchmark;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\Import\PurgeStrategy\PurgeStrategy;
use Icinga\Module\Director\Import\Sync;
use Exception;

class SyncRule extends DbObject implements ExportInterface
{
    protected $table = 'sync_rule';

    protected $keyName = 'rule_name';

    protected $autoincKeyName = 'id';

    protected $protectAutoinc = false;

    protected $defaultProperties = [
        'id'                 => null,
        'rule_name'          => null,
        'object_type'        => null,
        'update_policy'      => null,
        'purge_existing'     => null,
        'purge_action'       => null,
        'filter_expression'  => null,
        'sync_state'         => 'unknown',
        'last_error_message' => null,
        'last_attempt'       => null,
        'description'        => null,
    ];

    protected $stateProperties = [
        'sync_state',
        'last_error_message',
        'last_attempt',
    ];

    protected $booleans = [
        'purge_existing' => 'purge_existing',
    ];

    private $sync;

    private $purgeStrategy;

    private $filter;

    private $hasCombinedKey;

    /** @var SyncProperty[] */
    private $syncProperties;

    private $sourceKeyPattern;

    private $destinationKeyPattern;

    private $newSyncProperties;

    public function listInvolvedSourceIds()
    {
        if (! $this->hasBeenLoadedFromDb()) {
            return [];
        }

        $db = $this->getDb();
        return array_map('intval', array_unique(
            $db->fetchCol(
                $db->select()
                   ->from(['p' => 'sync_property'], 'p.source_id')
                   ->join(['s' => 'import_source'], 's.id = p.source_id', array())
                   ->where('rule_id = ?', $this->get('id'))
                   ->order('s.source_name')
            )
        ));
    }

    /**
     * @return array
     * @throws \Icinga\Exception\NotFoundError
     */
    public function fetchInvolvedImportSources()
    {
        $sources = [];

        foreach ($this->listInvolvedSourceIds() as $sourceId) {
            $sources[$sourceId] = ImportSource::loadWithAutoIncId($sourceId, $this->getConnection());
        }

        return $sources;
    }

    public function getLastSyncTimestamp()
    {
        if (! $this->hasBeenLoadedFromDb()) {
            return null;
        }

        $db = $this->getDb();
        $query = $db->select()->from(
            ['sr' => 'sync_run'],
            'sr.start_time'
        )->where('sr.rule_id = ?', $this->get('id'))
        ->order('sr.start_time DESC')
        ->limit(1);

        return $db->fetchOne($query);
    }

    public function getLastSyncRunId()
    {
        if (! $this->hasBeenLoadedFromDb()) {
            return null;
        }

        $db = $this->getDb();
        $query = $db->select()->from(
            ['sr' => 'sync_run'],
            'sr.id'
        )->where('sr.rule_id = ?', $this->get('id'))
        ->order('sr.start_time DESC')
        ->limit(1);

        return $db->fetchOne($query);
    }

    public function matches($row)
    {
        if ($this->get('filter_expression') === null) {
            return true;
        }

        return $this->filter()->matches($row);
    }

    /**
     * @param bool $apply
     * @return bool
     * @throws DuplicateKeyException
     */
    public function checkForChanges($apply = false)
    {
        $hadChanges = false;

        Benchmark::measure('Checking sync rule ' . $this->get('rule_name'));
        try {
            $this->set('last_attempt', date('Y-m-d H:i:s'));
            $this->set('sync_state', 'unknown');
            $sync = $this->sync();
            if ($sync->hasModifications()) {
                Benchmark::measure('Got modifications for sync rule ' . $this->get('rule_name'));
                $this->set('sync_state', 'pending-changes');
                if ($apply && $runId = $sync->apply()) {
                    Benchmark::measure('Successfully synced rule ' . $this->get('rule_name'));
                    $this->set('sync_state', 'in-sync');
                }

                $hadChanges = true;
            } else {
                Benchmark::measure('No modifications for sync rule ' . $this->get('rule_name'));
                $this->set('sync_state', 'in-sync');
            }

            $this->set('last_error_message', null);
        } catch (Exception $e) {
            $this->set('sync_state', 'failing');
            $this->set('last_error_message', $e->getMessage());
            // TODO: Store last error details / trace?
        }

        if ($this->hasBeenModified()) {
            $this->store();
        }

        return $hadChanges;
    }

    /**
     * @return IcingaObject[]
     * @throws IcingaException
     */
    public function getExpectedModifications()
    {
        return $this->sync()->getExpectedModifications();
    }

    /**
     * @return bool
     * @throws DuplicateKeyException
     */
    public function applyChanges()
    {
        return $this->checkForChanges(true);
    }

    public function getSourceKeyPattern()
    {
        if ($this->hasCombinedKey()) {
            return $this->sourceKeyPattern;
        } else {
            return null; // ??
        }
    }

    public function getDestinationKeyPattern()
    {
        if ($this->hasCombinedKey()) {
            return $this->destinationKeyPattern;
        } else {
            return null; // ??
        }
    }

    protected function sync()
    {
        if ($this->sync === null) {
            $this->sync = new Sync($this);
        }

        return $this->sync;
    }

    /**
     * @return Filter
     */
    public function filter()
    {
        if ($this->filter === null) {
            $this->filter = Filter::fromQueryString($this->get('filter_expression'));
        }

        return $this->filter;
    }

    public function purgeStrategy()
    {
        if ($this->purgeStrategy === null) {
            $this->purgeStrategy = $this->loadConfiguredPurgeStrategy();
        }

        return $this->purgeStrategy;
    }

    // TODO: Allow for more
    protected function loadConfiguredPurgeStrategy()
    {
        if ($this->get('purge_existing') === 'y') {
            return PurgeStrategy::load('ImportRunBased', $this);
        } else {
            return PurgeStrategy::load('PurgeNothing', $this);
        }
    }

    /**
     * @deprecated please use \Icinga\Module\Director\Data\Exporter
     * @return object
     */
    public function export()
    {
        $plain = $this->getProperties();
        $plain['originalId'] = $plain['id'];
        unset($plain['id']);

        foreach ($this->stateProperties as $key) {
            unset($plain[$key]);
        }
        $plain['properties'] = $this->exportSyncProperties();
        ksort($plain);

        return (object) $plain;
    }

    /**
     * @param object $plain
     * @param Db $db
     * @param bool $replace
     * @return static
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function import($plain, Db $db, $replace = false)
    {
        $properties = (array) $plain;
        if (isset($properties['originalId'])) {
            $id = $properties['originalId'];
            unset($properties['originalId']);
        } else {
            $id = null;
        }
        $name = $properties['rule_name'];

        if ($replace && $id && static::existsWithNameAndId($name, $id, $db)) {
            $object = static::loadWithAutoIncId($id, $db);
        } elseif ($replace && static::exists($name, $db)) {
            $object = static::load($name, $db);
        } elseif (static::existsWithName($name, $db)) {
            throw new DuplicateKeyException(
                'Sync Rule %s already exists',
                $name
            );
        } else {
            $object = static::create([], $db);
        }

        $object->newSyncProperties = $properties['properties'];
        unset($properties['properties']);
        $object->setProperties($properties);

        return $object;
    }

    /**
     * Flat object has 'properties', but setProperties() is not available in DbObject
     *
     * @return void
     */
    public function setSyncProperties(?array $value)
    {
        $this->newSyncProperties = $value;
    }

    public function getUniqueIdentifier()
    {
        return $this->get('rule_name');
    }

    /**
     * @throws DuplicateKeyException
     */
    protected function onStore()
    {
        parent::onStore();
        if ($this->newSyncProperties !== null) {
            $connection = $this->getConnection();
            $db = $connection->getDbAdapter();
            $myId = $this->get('id');
            if ($this->hasBeenLoadedFromDb()) {
                $db->delete(
                    'sync_property',
                    $db->quoteInto('rule_id = ?', $myId)
                );
            }

            foreach ($this->newSyncProperties as $property) {
                unset($property->rule_name);
                $property = SyncProperty::create((array) $property, $connection);
                $property->set('rule_id', $myId);
                $property->store();
            }
        }
    }

    /**
     * @deprecated
     * @return array
     */
    protected function exportSyncProperties()
    {
        $all = [];
        $db = $this->getDb();
        $sourceNames = $db->fetchPairs(
            $db->select()->from('import_source', ['id', 'source_name'])
        );

        foreach ($this->getSyncProperties() as $property) {
            $properties = $property->getProperties();
            $properties['source'] = $sourceNames[$properties['source_id']];
            unset($properties['id']);
            unset($properties['rule_id']);
            unset($properties['source_id']);
            ksort($properties);
            $all[] = (object) $properties;
        }

        return $all;
    }

    /**
     * Whether we have a combined key (e.g. services on hosts)
     *
     * @return bool
     */
    public function hasCombinedKey()
    {
        if ($this->hasCombinedKey === null) {
            $this->hasCombinedKey = false;

            // TODO: Move to Objects
            if ($this->get('object_type') === 'service') {
                $hasHost = false;
                $hasObjectName = false;
                $hasServiceSet = false;

                foreach ($this->getSyncProperties() as $key => $property) {
                    if ($property->destination_field === 'host') {
                        $hasHost = $property->source_expression;
                    }
                    if ($property->destination_field === 'service_set') {
                        $hasServiceSet = $property->source_expression;
                    }
                    if ($property->destination_field === 'object_name') {
                        $hasObjectName = $property->source_expression;
                    }
                }

                if ($hasHost !== false && $hasObjectName !== false) {
                    $this->hasCombinedKey = true;
                    $this->sourceKeyPattern = sprintf(
                        '%s!%s',
                        $hasHost,
                        $hasObjectName
                    );

                    $this->destinationKeyPattern = '${host}!${object_name}';
                } elseif ($hasServiceSet !== false && $hasObjectName !== false) {
                    $this->hasCombinedKey = true;
                    $this->sourceKeyPattern = sprintf(
                        '%s!%s',
                        $hasServiceSet,
                        $hasObjectName
                    );

                    $this->destinationKeyPattern = '${service_set}!${object_name}';
                }
            } elseif ($this->get('object_type') === 'serviceSet') {
                $hasHost = false;
                $hasObjectName = false;

                foreach ($this->getSyncProperties() as $key => $property) {
                    if ($property->destination_field === 'host') {
                        $hasHost = $property->source_expression;
                    }
                    if ($property->destination_field === 'object_name') {
                        $hasObjectName = $property->source_expression;
                    }
                }

                if ($hasHost !== false && $hasObjectName !== false) {
                    $this->hasCombinedKey = true;
                    $this->sourceKeyPattern = sprintf(
                        '%s!%s',
                        $hasHost,
                        $hasObjectName
                    );

                    $this->destinationKeyPattern = '${host}!${object_name}';
                }
            } elseif ($this->get('object_type') === 'datalistEntry') {
                $hasList = false;
                $hasName = false;

                foreach ($this->getSyncProperties() as $key => $property) {
                    if ($property->destination_field === 'list_id') {
                        $hasList = $property->source_expression;
                    }
                    if ($property->destination_field === 'entry_name') {
                        $hasName = $property->source_expression;
                    }
                }

                if ($hasList !== false && $hasName !== false) {
                    $this->hasCombinedKey = true;
                    $this->sourceKeyPattern = sprintf(
                        '%s!%s',
                        $hasList,
                        $hasName
                    );

                    $this->destinationKeyPattern = '${list_id}!${entry_name}';
                }
            }
        }

        return $this->hasCombinedKey;
    }

    public function hasSyncProperties()
    {
        $properties = $this->getSyncProperties();
        return ! empty($properties);
    }

    /**
     * @return SyncProperty[]
     */
    public function getSyncProperties()
    {
        if (! $this->hasBeenLoadedFromDb()) {
            return [];
        }

        if ($this->syncProperties === null) {
            $this->syncProperties = $this->fetchSyncProperties();
        }

        return $this->syncProperties;
    }

    public function fetchSyncProperties()
    {
        $db = $this->getDb();

        return SyncProperty::loadAll(
            $this->getConnection(),
            $db->select()
               ->from('sync_property')
               ->where('rule_id = ?', $this->get('id'))
               ->order('priority ASC')
        );
    }

    /**
     * TODO: implement in a generic way, this is duplicated code
     *
     * @param string $name
     * @param Db $connection
     * @api internal
     * @return bool
     */
    public static function existsWithName($name, Db $connection)
    {
        $db = $connection->getDbAdapter();

        return (string) $name === (string) $db->fetchOne(
            $db->select()
                ->from('sync_rule', 'rule_name')
                ->where('rule_name = ?', $name)
        );
    }

    /**
     * @param string $name
     * @param int $id
     * @param Db $connection
     * @api internal
     * @return bool
     */
    protected static function existsWithNameAndId($name, $id, Db $connection)
    {
        $db = $connection->getDbAdapter();
        $dummy = new static;
        $idCol = $dummy->autoincKeyName;
        $keyCol = $dummy->keyName;

        return (string) $id === (string) $db->fetchOne(
            $db->select()
                ->from($dummy->table, $idCol)
                ->where("$idCol = ?", $id)
                ->where("$keyCol = ?", $name)
        );
    }
}
