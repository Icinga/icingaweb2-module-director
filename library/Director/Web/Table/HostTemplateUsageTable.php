<?php

namespace Icinga\Module\Director\Web\Table;

class HostTemplateUsageTable extends TemplateUsageTable
{
    public function getTypes()
    {
        return [
            'templates'  => $this->translate('Templates'),
            'objects'    => $this->translate('Objects'),
        ];
    }

    protected function getTypeSummaryDefinitions()
    {
        return [
            'templates'  => $this->getSummaryLine('template'),
            'objects'    => $this->getSummaryLine('object'),
        ];
    }
}
