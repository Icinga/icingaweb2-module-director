<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Db;

class DependencyTemplateUsageTable extends TemplateUsageTable
{
    public function getTypes()
    {
        return [
            'templates'  => $this->translate('Templates'),
            'applyrules' => $this->translate('Apply Rules'),
        ];
    }

    protected function getSummaryTables(string $templateType, Db $connection)
    {
        return [
            'templates'  => TemplatesTable::create(
                $templateType,
                $connection
            ),
            'applyrules' => ApplyRulesTable::create($templateType, $connection)
                ->setBranchUuid($this->branchUuid)
        ];
    }
}
