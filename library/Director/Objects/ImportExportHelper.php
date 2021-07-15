<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db;

/**
 * Helper class, allows to reduce duplicate code. Might be moved elsewhere
 * afterwards
 */
class ImportExportHelper
{
    /**
     * Does not support every type out of the box
     *
     * @param IcingaObject $object
     * @return object
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function simpleExport(IcingaObject $object)
    {
        $props = (array) $object->toPlainObject();
        $props['fields'] = static::fetchFields($object);
        ksort($props); // TODO: ksort in toPlainObject?

        return (object) $props;
    }

    public static function fetchFields(IcingaObject $object)
    {
        return static::loadFieldReferences(
            $object->getConnection(),
            $object->getShortTableName(),
            $object->get('id')
        );
    }

    /**
     * @param Db $connection
     * @param string $type Warning: this will not be validated.
     * @param int $id
     * @return array
     */
    public static function loadFieldReferences(Db $connection, $type, $id)
    {
        $db = $connection->getDbAdapter();
        $res = $db->fetchAll(
            $db->select()->from([
                'f' => "icinga_${type}_field"
            ], [
                'f.datafield_id',
                'f.is_required',
                'f.var_filter',
            ])->join(['df' => 'director_datafield'], 'df.id = f.datafield_id', [])
                ->where("${type}_id = ?", $id)
                ->order('varname ASC')
        );

        if (empty($res)) {
            return [];
        }

        foreach ($res as $field) {
            $field->datafield_id = (int) $field->datafield_id;
        }
        return $res;
    }
}
