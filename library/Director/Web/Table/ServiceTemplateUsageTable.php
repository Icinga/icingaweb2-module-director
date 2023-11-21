<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;

class ServiceTemplateUsageTable extends TemplateUsageTable
{
    public function getTypes()
    {
        return [
            'templates'  => $this->translate('Templates'),
            'objects'    => $this->translate('Objects'),
            'applyrules' => $this->translate('Apply Rules'),
            'setmembers' => $this->translate('Set Members'),
        ];
    }

    protected function getSummaryTables(string $templateType, Db $connection)
    {
        $auth = Auth::getInstance();
        return [
            'templates'  => TemplatesTable::create(
                $templateType,
                $connection
            ),
            'objects'    => ObjectsTable::create($templateType, $connection, $this->auth)
                ->setBranchUuid($this->branchUuid),
            'applyrules' => ApplyRulesTable::create($templateType, $connection)
                ->setBranchUuid($this->branchUuid),
            'setmembers' => ObjectsTableSetMembers::create(
                $templateType,
                $connection,
                $auth
            )
        ];
    }
}
