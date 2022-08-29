<?php

namespace Icinga\Module\Director\Web\Table;

use Ramsey\Uuid\UuidInterface;

trait TableWithBranchSupport
{

    /** @var UuidInterface|null */
    protected $branchUuid;

    public function setBranchUuid(UuidInterface $uuid = null)
    {
        $this->branchUuid = $uuid;

        return $this;
    }

    protected function branchifyColumns($columns)
    {
        $result = [
            'uuid' => 'COALESCE(o.uuid, bo.uuid)'
        ];
        $ignore = ['o.id'];
        foreach ($columns as $alias => $column) {
            if (substr($column, 0, 2) === 'o.' && ! in_array($column, $ignore)) {
                // bo.column, o.column
                $column = "COALESCE(b$column, $column)";
            }

            // Used in Service Tables:
            if ($column === 'h.object_name' && $alias = 'host') {
                $column = "COALESCE(bo.host, $column)";
            }

            $result[$alias] = $column;
        }

        return $result;
    }

    protected function stripSearchColumnAliases()
    {
        foreach ($this->searchColumns as &$column) {
            $column = preg_replace('/^[a-z]+\./', '', $column);
        }
    }
}
