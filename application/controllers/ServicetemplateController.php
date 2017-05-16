<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Web\Controller\SimpleController;
use Icinga\Module\Director\Web\Table\ServicesOnHostsTable;
use ipl\Html\Html;

class ServicetemplateController extends SimpleController
{
    public function hostsAction()
    {
        $this->addSingleTab($this->translate('Hosts using this service Template'));
        $this->content()->add(
            new ServicesOnHostsTable($this->db())
        );
    }

    public function servicesAction()
    {
        $template = $this->requireTemplate();
        $this->addSingleTab(
            $this->translate('Single Services')
        )->addTitle(
            $this->translate('Services based on %s'),
            $template->getObjectName()
        );

        $this->content()->add(
            new ServicesOnHostsTable($this->db())
        );
    }

    public function usageAction()
    {
        $template = $this->requireTemplate();

        $this->addSingleTab(
            $this->translate('Service Template Usage')
        )->addTitle(
            $this->translate('Template: %s'),
            $template->getObjectName()
        );

        $this->content()->add(
            Html::tag('pre', null, print_r(
                $this->getUsageSummary($template),
                1
            ))
        );
    }

    protected function getUsageSummary(IcingaService $template)
    {
        $ids = $template->templateResolver()->listInheritancePathIds();
        $db = $this->db()->getDbAdapter();

        $query = $db->select()->from(
            ['s' => 'icinga_service'],
            [
                'cnt_templates'   => $this->getSummaryLine('template'),
                'cnt_objects'     => $this->getSummaryLine('object'),
                'cnt_apply_rules' => $this->getSummaryLine('apply', 's.service_set_id IS NULL'),
                'cnt_set_members' => $this->getSummaryLine('apply', 's.service_set_id IS NOT NULL'),
            ]
        )->joinLeft(
            ['ps' => 'icinga_service_inheritance'],
            'ps.service_id = s.id',
            []
        )->where('ps.parent_service_id IN (?)', $ids);

        return $db->fetchRow($query);
    }

    protected function getSummaryLine($type, $extra = null)
    {
        if ($extra !== null) {
            $extra = " AND $extra";
        }
        return "COALESCE(SUM(CASE WHEN s.object_type = '${type}'${extra} THEN 1 ELSE 0 END), 0)";
    }

    protected function requireTemplate()
    {
        return IcingaService::load([
            'object_name' => $this->params->get('name')
        ], $this->db());
    }
}
