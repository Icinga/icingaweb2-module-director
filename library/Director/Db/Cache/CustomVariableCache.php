<?php

namespace Icinga\Module\Director\Db\Cache;

use Icinga\Module\Director\CustomVariable\CustomVariables;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;

class CustomVariableCache
{
    protected $type;

    protected $rowsById = array();

    protected $varsById = array();

    public function __construct(IcingaObject $object)
    {
        $connection = $object->getConnection();
        $db = $connection->getDbAdapter();

        $columns = array(
            'id'       => sprintf('v.%s', $object->getVarsIdColumn()),
            'varname'  => 'v.varname',
            'varvalue' => 'v.varvalue',
            'format'   => 'v.format',
            'checksum' => 'v.checksum',
            'rendered' => 'iv.rendered',
        );

        $objectCol = 'v.' . $object->getShortTableName() . '_id';
        $query = $db->select()->from(
            array('v' => $object->getVarsTableName()),
            $columns
        )->joinLeft(
            array('iv' => 'icinga_var'),
            'v.checksum = iv.checksum',
            array()
        )->order($objectCol)->order('v.varname');

        foreach ($db->fetchAll($query) as $row) {

            $id = $row->id;
            unset($row->id);

            if (is_resource($row->checksum)) {
                $row->checksum = stream_get_contents($row->checksum);
            }

            if (array_key_exists($id, $this->rowsById)) {
                $this->rowsById[$id][] = $row;
            } else {
                $this->rowsById[$id] = array($row);
            }
        }
    }

    public function renderForObject(IcingaObject $object)
    {
        $id = $object->id;
        if (array_key_exists($id, $this->rowsById)){
            $rows = & $this->rowsById[$id];

            if ($rows[0]->rendered === null) {
                return $this->getVarsForObject($object)->toConfigString($object->isApplyRule());
            } else {
                $str = '';
                foreach ($rows as $row) {
                    $str .= $row->rendered;
                }

                return $str;
            }
        } else {
            return '';
        }
    }

    public function getVarsForObject(IcingaObject $object)
    {
        $id = $object->id;

        if (array_key_exists($id, $this->rowsById)) {
            if (! array_key_exists($id, $this->varsById)) {
                $this->varsById[$id] = CustomVariables::forStoredRows(
                    $this->rowsById[$id]
                );
            }

            return $this->varsById[$id];
        } else {
            return new CustomVariables();
        }
    }

    public function __destruct()
    {
        unset($this->db);
    }
}
