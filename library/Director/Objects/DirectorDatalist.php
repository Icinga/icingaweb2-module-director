<?php

namespace Icinga\Module\Director\Objects;

use Exception;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;

class DirectorDatalist extends DbObject implements ExportInterface
{
    protected $table = 'director_datalist';
    protected $keyName = 'list_name';
    protected $autoincKeyName = 'id';
    protected $uuidColumn = 'uuid';

    protected $defaultProperties = [
        'id'            => null,
        'uuid'          => null,
        'list_name'     => null,
        'owner'         => null
    ];

    /** @var DirectorDatalistEntry[] */
    protected $storedEntries;

    public function getUniqueIdentifier()
    {
        return $this->get('list_name');
    }

    public function setEntries($entries)
    {
        $existing = $this->getStoredEntries();

        $new = [];
        $seen = [];
        $modified = false;

        foreach ($entries as $entry) {
            $name = $entry->entry_name;
            $entry = DirectorDatalistEntry::create((array) $entry);
            $seen[$name] = true;
            if (isset($existing[$name])) {
                $existing[$name]->replaceWith($entry);
                if (! $modified && $existing[$name]->hasBeenModified()) {
                    $modified = true;
                }
            } else {
                $modified = true;
                $new[] = $entry;
            }
        }

        foreach (array_keys($existing) as $key) {
            if (! isset($seen[$key])) {
                $existing[$key]->markForRemoval();
                $modified = true;
            }
        }

        foreach ($new as $entry) {
            $existing[$entry->get('entry_name')] = $entry;
        }

        if ($modified) {
            $this->hasBeenModified = true;
        }

        $this->storedEntries = $existing;
        ksort($this->storedEntries);

        return $this;
    }

    protected function beforeDelete()
    {
        if ($this->hasBeenUsed()) {
            throw new Exception(
                sprintf(
                    "Cannot delete '%s', as the datalist '%s' is currently being used.",
                    $this->get('list_name'),
                    $this->get('list_name')
                )
            );
        }
    }

    protected function hasBeenUsed()
    {
        $datalistType = 'Icinga\\Module\\Director\\DataType\\DataTypeDatalist';
        $db = $this->getDb();

        $dataFieldsCheck = $db->select()
            ->from(['df' =>'director_datafield'], ['varname'])
            ->join(
                ['dfs' => 'director_datafield_setting'],
                'dfs.datafield_id = df.id AND dfs.setting_name = \'datalist_id\'',
                []
            )
            ->join(
                ['l' => 'director_datalist'],
                'l.id = dfs.setting_value',
                []
            )
            ->where('datatype = ?', $datalistType)
            ->where('setting_value = ?', $this->get('id'));

        if ($db->fetchOne($dataFieldsCheck)) {
            return true;
        }

        $syncCheck = $db->select()
            ->from(['sp' =>'sync_property'], ['source_expression'])
            ->where('sp.destination_field = ?', 'list_id')
            ->where('sp.source_expression = ?', $this->get('id'));

        if ($db->fetchOne($syncCheck)) {
            return true;
        }

        return false;
    }

    /**
     * @throws DuplicateKeyException
     */
    public function onStore()
    {
        if ($this->storedEntries) {
            $db = $this->getConnection();
            $removedKeys = [];
            $myId = $this->get('id');

            foreach ($this->storedEntries as $key => $entry) {
                if ($entry->shouldBeRemoved()) {
                    $entry->delete();
                    $removedKeys[] = $key;
                } else {
                    if (! $entry->hasBeenLoadedFromDb()) {
                        $entry->set('list_id', $myId);
                    }
                    $entry->set('list_id', $myId);
                    $entry->store($db);
                }
            }

            foreach ($removedKeys as $key) {
                unset($this->storedEntries[$key]);
            }
        }
    }

    protected function getStoredEntries()
    {
        if ($this->storedEntries === null) {
            if ($id = $this->get('id')) {
                $this->storedEntries = DirectorDatalistEntry::loadAllForList($this);
                ksort($this->storedEntries);
            } else {
                $this->storedEntries = [];
            }
        }

        return $this->storedEntries;
    }
}
