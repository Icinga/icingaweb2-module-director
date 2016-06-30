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
        $db = $object->getConnection()->getDbAdapter();

        $query = $db->select()->from(
            array('v' => $object->getVarsTableName()),
            array(
                'id'       => sprintf('v.%s', $object->getVarsIdColumn()),
                'varname'  => 'v.varname',
                'varvalue' => 'v.varvalue',
                'format'   => 'v.format',
            )
        );

        foreach ($db->fetchAll($query) as $row) {

            $id = $row->id;
            unset($row->id);

            if (array_key_exists($id, $this->rowsById)) {
                $this->rowsById[$id][] = $row;
            } else {
                $this->rowsById[$id] = array($row);
            }
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
