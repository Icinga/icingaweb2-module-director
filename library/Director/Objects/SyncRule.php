<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Application\Benchmark;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Import\Sync;
use Exception;

class SyncRule extends DbObject
{
    protected $table = 'sync_rule';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'                 => null,
        'rule_name'          => null,
        'object_type'        => null,
        'update_policy'      => null,
        'purge_existing'     => null,
        'filter_expression'  => null,
        'sync_state'         => 'unknown',
        'last_error_message' => null,
        'last_attempt'       => null,
    );

    private $sync;

    private $filter;

    public function listInvolvedSourceIds()
    {
        if (! $this->hasBeenLoadedFromDb()) {
            return array();
        }

        $db = $this->getDb();
        return array_map('intval', array_unique(
            $db->fetchCol(
                $db->select()
                   ->from(array('p' => 'sync_property'), 'p.source_id')
                   ->join(array('s' => 'import_source'), 's.id = p.source_id', array())
                   ->where('rule_id = ?', $this->id)
                   ->order('s.source_name')
            )
        ));
    }

    public function getPriorityForNextProperty()
    {
        if (! $this->hasBeenLoadedFromDb()) {
            return 1;
        }
        
        $db = $this->getDb();
        return $db->fetchOne(
            $db->select()
                ->from(
                    array('p' => 'sync_property'),
                    array('priority' => '(CASE WHEN MAX(p.priority) IS NULL THEN 1 ELSE MAX(p.priority) + 1 END)')
                )->where('p.rule_id = ?', $this->id)
        );
    }

    public function matches($row)
    {
        if ($this->filter_expression === null) {
            return true;
        }

        return $this->filter()->matches($row);
    }

    public function checkForChanges($apply = false)
    {
        $hadChanges = false;

        Benchmark::measure('Checking sync rule ' . $this->rule_name);
        try {
            $sync = $this->sync();
            if ($sync->hasModifications()) {
                Benchmark::measure('Got modifications for sync rule ' . $this->rule_name);
                $this->sync_state = 'pending-changes';
                if ($apply && $sync->apply()) {
                    Benchmark::measure('Successfully synced rule ' . $this->rule_name);
                    $this->sync_state = 'in-sync';
                }

                $hadChanges = true;

            } else {
                Benchmark::measure('No modifications for sync rule ' . $this->rule_name);
                $this->sync_state = 'in-sync';
            }

            $this->last_error_message = null;
        } catch (Exception $e) {
            $this->sync_state = 'failing';
            $this->last_error_message = $e->getMessage();
        }

        if ($this->hasBeenModified()) {
            $this->store();
        }

        return $hadChanges;
    }

    public function applyChanges()
    {
        return $this->checkForChanges(true);
    }

    protected function sync()
    {
        if ($this->sync === null) {
            $this->sync = new Sync($this);
        }

        return $this->sync;
    }

    protected function filter()
    {
        if ($this->filter === null) {
            $this->filter = Filter::fromQueryString($this->filter_expression);
        }

        return $this->filter;
    }

    public function fetchSyncProperties()
    {
        $db = $this->getDb();
        return SyncProperty::loadAll(
            $this->getConnection(),
            $db->select()
               ->from('sync_property')
               ->where('rule_id = ?', $this->id)
               ->order('priority DESC')
        );

        return $this->syncProperties;
    }
}
