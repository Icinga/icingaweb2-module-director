<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Restriction\BetaHostgroupRestriction;
use Icinga\Module\Director\Tables\IcingaHostTable;
use Icinga\Module\Director\Web\Controller\ObjectsController;

class HostsController extends ObjectsController
{
    protected $multiEdit = array(
        'imports',
        'groups',
        'disabled'
    );

    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/hosts');
    }

    public function editAction()
    {
        $url = clone($this->getRequest()->getUrl());
        $url->setPath('director/hosts/addservice');

        parent::editAction();

        $this->addActionLink($this->view->qlink(
            $this->translate('Add Service'),
            $url,
            null,
            array('class' => 'icon-plus')
        ));
    }

    protected function addActionLink($link)
    {
        if (! is_array($this->view->actionLinks)) {
            $this->view->actionLinks = array();
        }

        $this->view->actionLinks[] = $link;
        return $this;
    }

    public function addserviceAction()
    {
        $this->singleTab($this->translate('Add Service'));
        $filter = Filter::fromQueryString($this->params->toString());

        $objects = array();
        $db = $this->db();
        /** @var $filter FilterChain */
        foreach ($filter->filters() as $sub) {
            /** @var $sub FilterChain */
            foreach ($sub->filters() as $ex) {
                /** @var $ex FilterChain|FilterExpression */
                if ($ex->isExpression() && $ex->getColumn() === 'name') {
                    $name = $ex->getExpression();
                    $objects[$name] = IcingaHost::load($name, $db);
                }
            }
        }
        $this->view->title = sprintf(
            $this->translate('Add service to %d hosts'),
            count($objects)
        );

        $this->view->form = $this->loadForm('IcingaAddServiceToMultipleHosts')
            ->setHosts($objects)
            ->setDb($this->db())
            ->handleRequest();

        $this->setViewScript('objects/form');
    }

    /**
     * @param IcingaHostTable $table
     */
    protected function applyTableFilters($table)
    {
        $table->addObjectRestriction(
            new BetaHostgroupRestriction($this->db(), $this->Auth())
        );
    }
}
