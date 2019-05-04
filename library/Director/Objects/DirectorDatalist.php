<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;

class DirectorDatalist extends DbObject implements ExportInterface
{
    protected $table = 'director_datalist';

    protected $keyName = 'list_name';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'            => null,
        'list_name'     => null,
        'owner'         => null
    );

    /** @var DirectorDatalistEntry[] */
    protected $storedEntries;

    public function getUniqueIdentifier()
    {
        return $this->get('list_name');
    }

    /**
     * @param $plain
     * @param Db $db
     * @param bool $replace
     * @return static
     * @throws \Icinga\Exception\NotFoundError
     * @throws DuplicateKeyException
     */
    public static function import($plain, Db $db, $replace = false)
    {
        $properties = (array) $plain;
        if (isset($properties['originalId'])) {
            unset($properties['originalId']);
        } else {
            $id = null;
        }
        $name = $properties['list_name'];

        if ($replace && static::exists($name, $db)) {
            $object = static::load($name, $db);
        } elseif (static::exists($name, $db)) {
            throw new DuplicateKeyException(
                'Data List %s already exists',
                $name
            );
        } else {
            $object = static::create([], $db);
        }
        $object->setProperties($properties);

        return $object;
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

    public function export()
    {
        $plain = (object) $this->getProperties();
        $plain->originalId = $plain->id;
        unset($plain->id);

        $plain->entries = [];
        foreach ($this->getStoredEntries() as $key => $entry) {
            if ($entry->shouldBeRemoved()) {
                continue;
            }
            $plainEntry = (object) $entry->getProperties();
            unset($plainEntry->list_id);

            $plain->entries[] = $plainEntry;
        }

        return $plain;
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
