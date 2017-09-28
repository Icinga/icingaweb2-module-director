<?php

namespace Icinga\Module\Director\Web\Table;

class DependencyTemplateUsageTable extends TemplateUsageTable
{
    public function getTypes()
    {
        return [
            'templates'  => $this->translate('Templates'),
            'applyrules' => $this->translate('Apply Rules'),
        ];
    }

    protected function getTypeSummaryDefinitions()
    {
        return [
            'templates'  => $this->getSummaryLine('template'),
            'applyrules' => $this->getSummaryLine('apply'),
        ];
    }
}
