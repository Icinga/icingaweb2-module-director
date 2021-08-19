<?php

namespace Icinga\Module\Director\Web\Table;

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

    protected function getTypeSummaryDefinitions()
    {
        return [
            'templates'  => $this->getSummaryLine('template'),
            'objects'    => $this->getSummaryLine('object', 'o.service_set_id IS NULL AND o.host_id IS NOT NULL'),
            'applyrules' => $this->getSummaryLine('apply', 'o.service_set_id IS NULL'),
            // TODO: re-enable
            'setmembers' => $this->getSummaryLine('object', 'o.service_set_id IS NOT NULL'),
        ];
    }
}
