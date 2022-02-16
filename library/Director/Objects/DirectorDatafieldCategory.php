<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Exception\DuplicateKeyException;

class DirectorDatafieldCategory extends DbObject
{
    protected $table = 'director_datafield_category';

    protected $keyName = 'category_name';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = [
        'id'            => null,
        'category_name' => null,
        'description'   => null,
    ];

    /**
     * @return object
     */
    public function export()
    {
        $plain = (object) $this->getProperties();
        $plain->originalId = $plain->id;
        unset($plain->id);
        return $plain;
    }

    /**
     * @param $plain
     * @param Db $db
     * @param bool $replace
     * @return static
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function import($plain, Db $db, $replace = false)
    {
        $properties = (array) $plain;
        unset($properties['originalId']);
        $key = $properties['category_name'];

        if ($replace && static::exists($key, $db)) {
            $object = static::load($key, $db);
        } elseif (static::exists($key, $db)) {
            throw new DuplicateKeyException(
                'Cannot import, DatafieldCategory "%s" already exists',
                $key
            );
        } else {
            $object = static::create([], $db);
        }

        $object->setProperties($properties);

        return $object;
    }
}
