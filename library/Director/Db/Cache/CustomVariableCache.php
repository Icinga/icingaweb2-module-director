<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

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

        $query = $db->select()->from(
            ['v' => $object->getVarsTableName()],
            [
                'id'            => sprintf('v.%s', $object->getVarsIdColumn()),
                'varname'       => 'v.varname',
                'varvalue'      => 'v.varvalue',
                'format'        => 'v.format',
                'property_uuid' => 'v.property_uuid',
            ]
        );

        foreach ($db->fetchAll($query) as $row) {
            $id = $row->id;
            unset($row->id);

            // hash it here instead of in SQL, some MySQL versions dropped SHA1()
            $row->checksum = sha1($row->varvalue . ';' . $row->format, true);

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
