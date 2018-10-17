<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\IcingaServiceSetForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Web\Table\IcingaServiceSetHostTable;
use Icinga\Module\Director\Web\Table\IcingaServiceSetServiceTable;
use Icinga\Module\Director\Web\Table\IcingaServiceSetAppliedHostsTable;
use dipl\Html\Link;

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
        $content = $this->content();
        $this->tabs()->activate('hosts');
        $this->addTitle(
            $this->translate('Hosts using this set: %s'),
            $set->getObjectName()
        );

        $direct_assign = IcingaServiceSetHostTable::load($set);

        if (count($direct_assign)) {
            $content->add($direct_assign);
        }

        $apply_filter = IcingaServiceSetAppliedHostsTable::load($set);

        if (count($apply_filter)) {
            $content->add($apply_filter);
        }

        if (count($direct_assign) + count($apply_filter) == 0) {
            $content->add($this->translate('No hosts are currently assigned this serviceset.'));
        }
    }

    protected function addServiceSetTabs()
    {
        $tabs = $this->tabs();
        $name = $this->object->getObjectName();
        $tabs->add('services', [
            'url'       => 'director/serviceset/services',
            'urlParams' => ['name' => $name],
            'label'     => 'Services'
        ])->add('hosts', [
            'url'       => 'director/serviceset/hosts',
            'urlParams' => ['name' => $name],
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
