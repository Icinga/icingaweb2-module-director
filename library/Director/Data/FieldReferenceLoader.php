<?php

namespace Icinga\Module\Director\Data;

use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;

class FieldReferenceLoader
{
    /** @var Adapter|\Zend_Db_Adapter_Abstract */
    protected $db;

    public function __construct(Db $connection)
    {
        $this->db = $connection->getDbAdapter();
    }

    /**
     * @param int $id
     * @return array
     */
    public function loadFor(IcingaObject $object)
    {
        $db = $this->db;
        $id = $object->get('id');
        if ($id === null) {
            return [];
        }
        $type = $object->getShortTableName();
        $res = $db->fetchAll(
            $db->select()->from(['f' => "icinga_{$type}_field"], [
                'f.datafield_id',
                'f.is_required',
                'f.var_filter',
            ])->join(['df' => 'director_datafield'], 'df.id = f.datafield_id', [])
                ->where("{$type}_id = ?", (int) $id)
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
