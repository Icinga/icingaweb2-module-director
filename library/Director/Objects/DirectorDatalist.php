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
            $id = $properties['originalId'];
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
        if ($id !== null) {
            $object->reallySet('id', $id);
        }

        return $object;
    }

    public function export()
    {
        $plain = (object) $this->getProperties();
        $plain->originalId = $plain->id;
        unset($plain->id);

        $plain->entries = [];
        $entries = DirectorDatalistEntry::loadAllForList($this);
        foreach ($entries as $key => $entry) {
            $plainEntry = (object) $entry->getProperties();
            unset($plainEntry->list_id);

            $plain->entries[] = $plainEntry;
        }

        return $plain;
    }
}
