<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Db\Branch\Branch;
use Ramsey\Uuid\UuidInterface;

trait TableWithBranchSupport
{
    /** @var UuidInterface|null */
    protected $branchUuid;

    /**
     * Convenience method, only UUID is required
     *
     * @param Branch|null $branch
     * @return $this
     */
    public function setBranch(Branch $branch = null)
    {
        if ($branch && $branch->isBranch()) {
            $this->setBranchUuid($branch->getUuid());
        }

        return $this;
    }

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
        $ignore = ['o.id', 'os.id', 'o.service_set_id', 'os.host_id'];
        foreach ($columns as $alias => $column) {
            if (substr($column, 0, 2) === 'o.' && ! in_array($column, $ignore)) {
                // bo.column, o.column
                $column = "COALESCE(b$column, $column)";
            }
            if (substr($column, 0, 3) === 'os.' && ! in_array($column, $ignore)) {
                // bo.column, o.column
                $column = "COALESCE(b$column, $column)";
            }

            // Used in Service Tables:
            if ($column === 'h.object_name' && $alias = 'host') {
                $column = "COALESCE(bo.host, $column)";
            }

            $result[$alias] = $column;
        }
        if (isset($result['count_services'])) {
            $result['count_services'] = 'COUNT(DISTINCT COALESCE(o.uuid, bo.uuid))';
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
