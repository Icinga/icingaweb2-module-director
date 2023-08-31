<?php

namespace Icinga\Module\Director\Db\Cache;

use Icinga\Application\Benchmark;
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
        Benchmark::measure('Initializing CustomVariableCache');
        $connection = $object->getConnection();
        $db = $connection->getDbAdapter();

        $columns = array(
            'id'       => sprintf('v.%s', $object->getVarsIdColumn()),
            'varname'  => 'v.varname',
            'varvalue' => 'v.varvalue',
            'format'   => 'v.format',
            'checksum' => '(NULL)',
        );

        if ($connection->isPgsql()) {
            if ($connection->hasPgExtension('pgcrypto')) {
                $columns['checksum'] = "DIGEST(v.varvalue || ';' || v.format, 'sha1')";
            }
        } else {
            $columns['checksum'] = "UNHEX(SHA1(v.varvalue || ';' || v.format))";
        }

        $query = $db->select()->from(
            array('v' => $object->getVarsTableName()),
            $columns
        );

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

        Benchmark::measure('Filled CustomVariableCache');
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
}
