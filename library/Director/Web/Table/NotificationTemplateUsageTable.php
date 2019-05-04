<?php

namespace Icinga\Module\Director\Web\Table;

class NotificationTemplateUsageTable extends TemplateUsageTable
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
            'applyrules' => $this->getSummaryLine('apply', 'o.host_id IS NULL'),
        ];
    }
}
