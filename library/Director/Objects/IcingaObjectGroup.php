<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;

abstract class IcingaObjectGroup extends IcingaObject implements ExportInterface
{
    protected $supportsImports = true;

    protected $supportedInLegacy = true;

    protected $uuidColumn = 'uuid';

    protected $defaultProperties = [
        'id'            => null,
        'uuid'          => null,
        'object_name'   => null,
        'object_type'   => null,
        'disabled'      => 'n',
        'display_name'  => null,
        'assign_filter' => null,
    ];

    public function getUniqueIdentifier()
    {
        return $this->getObjectName();
    }

    /**
     * @return object
     * @throws \Icinga\Exception\NotFoundError
     */
    public function export()
    {
        return $this->toPlainObject();
    }

    /**
     * @param $plain
     * @param Db $db
     * @param bool $replace
     * @return IcingaObjectGroup
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function import($plain, Db $db, $replace = false)
    {
        $properties = (array) $plain;
        $name = $properties['object_name'];
        $key = $name;

        if ($replace && static::exists($key, $db)) {
            $object = static::load($key, $db);
        } elseif (static::exists($key, $db)) {
            throw new DuplicateKeyException(
                'Group "%s" already exists',
                $name
            );
        } else {
            $object = static::create([], $db);
        }

        $object->setProperties($properties);

        return $object;
    }

    protected function prefersGlobalZone()
    {
        return true;
    }
}
