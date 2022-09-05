<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Forms\IcingaServiceSetForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Web\Table\IcingaHostsMatchingFilterTable;
use Icinga\Module\Director\Web\Table\IcingaServiceSetHostTable;
use Icinga\Module\Director\Web\Table\IcingaServiceSetServiceTable;
use gipfl\IcingaWeb2\Link;

class ServicesetController extends ObjectController
{
    /** @var IcingaHost */
    protected $host;

    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/servicesets');
    }

    public function init()
    {
        if (null !== ($host = $this->params->get('host'))) {
            $this->host = IcingaHost::load($host, $this->db());
        }

        parent::init();
        if ($this->object) {
            $this->addServiceSetTabs();
        }
    }

    protected function onObjectFormLoaded(DirectorObjectForm $form)
    {
        if ($this->host) {
            /** @var IcingaServiceSetForm $form */
            $form->setHost($this->host);
        }
    }

    public function addAction()
    {
        parent::addAction();
        if ($this->host) {
            $this->addTitle(
                $this->translate('Add a service set to "%s"'),
                $this->host->getObjectName()
            );
        }
    }

    public function servicesAction()
    {
        /** @var IcingaServiceSet $set */
        $set = $this->object;
        $name = $set->getObjectName();
        $this->tabs()->activate('services');
        $this->addTitle(
            $this->translate('Services in this set: %s'),
            $name
        );
        $this->actions()->add(Link::create(
            $this->translate('Add service'),
            'director/service/add',
            ['set' => $name],
            ['class' => 'icon-plus']
        ));

        IcingaServiceSetServiceTable::load($set)->renderTo($this);
    }

    public function hostsAction()
    {
        /** @var IcingaServiceSet $set */
        $set = $this->object;
        $this->tabs()->activate('hosts');
        $this->addTitle(
            $this->translate('Hosts using this set: %s'),
            $set->getObjectName()
        );

        $table = IcingaServiceSetHostTable::load($set);
        if ($table->count()) {
            $table->renderTo($this);
        }
        $filter = $set->get('assign_filter');
        if ($filter !== null && \strlen($filter) > 0) {
            $this->content()->add(
                IcingaHostsMatchingFilterTable::load(Filter::fromQueryString($filter), $this->db())
            );
        }
    }

    protected function addServiceSetTabs()
    {
        if ($this->branch->isBranch()) {
            return $this;
        }
        $hexUuid = $this->object->getUniqueId()->toString();
        $tabs = $this->tabs();
        $tabs->add('services', [
            'url'       => 'director/serviceset/services',
            'urlParams' => ['uuid' => $hexUuid],
            'label'     => 'Services'
        ])->add('hosts', [
            'url'       => 'director/serviceset/hosts',
            'urlParams' => ['uuid' => $hexUuid],
            'label'     => 'Hosts'
        ]);

        return $this;
    }

    protected function loadObject()
    {
        if ($this->object === null) {
            if (null !== ($name = $this->params->get('name'))) {
                $params = ['object_name' => $name];
                $db = $this->db();

                if ($this->host) {
                    $params['host_id'] = $this->host->get('id');
                }

                $this->object = IcingaServiceSet::load($params, $db);
            } else {
                parent::loadObject();
            }
        }

        return $this->object;
    }
}
