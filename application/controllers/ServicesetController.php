<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Web\Controller\ObjectController;
use ipl\Html\Link;

class ServicesetController extends ObjectController
{
    protected $host;

    public function init()
    {
        if ($host = $this->params->get('host')) {
            $this->host = IcingaHost::load($host, $this->db());
        }

        parent::init();
        if ($this->object) {
            $tabs = $this->tabs();
            $tabs->add('services', array(
                'url'       => 'director/serviceset/services',
                'urlParams' => array('name' => $this->object->object_name),
                'label'     => 'Services'
            ));
            $tabs->add('hosts', array(
                'url'       => 'director/serviceset/hosts',
                'urlParams' => array('name' => $this->object->object_name),
                'label'     => 'Hosts'
            ));
        }
    }

    public function loadForm($name)
    {
        $form = parent::loadForm($name);
        if ($name === 'icingaServiceSet' && $this->host) {
            $form->setHost($this->host);
        }

        return $form;
    }

    public function addAction()
    {
        parent::addAction();
        if ($this->host) {
            $this->addTitle(
                $this->translate('Add a service set to "%s"'),
                $this->host->object_name
            );
        }
    }

    public function servicesAction()
    {
        $db = $this->db();
        $set = $this->object;
        $this->tabs()->activate('services');
        $this->addTitle(
            $this->translate('Services in this set: %s'),
            $set->object_name
        );
        $this->actions()->add(Link::create(
            $this->translate('Add service'),
            'director/service/add',
            ['set' => $set->object_name],
            ['class' => 'icon-plus']
        ));

        // TODO!!
        $this->view->stayHere = true;


        $this->content()->add(
            $this->loadTable('IcingaServiceSetService')
                ->setServiceSet($set)
                ->setConnection($db)
        );
    }

    public function hostsAction()
    {
        $db = $this->db();
        $set = $this->object;
        $this->tabs()->activate('hosts');
        $this->addTitle(
            $this->translate('Hosts using this set: %s'),
            $set->object_name
        );

        $this->content()->add(
            $this->loadTable('IcingaServiceSetHost')
            ->setServiceSet($set)
            ->setConnection($db)
        );
    }

    protected function loadObject()
    {
        if ($this->object === null) {
            if ($name = $this->params->get('name')) {
                $params = array('object_name' => $name);
                $db = $this->db();

                if ($this->host) {
                    $this->view->host = $this->host;
                    $params['host_id'] = $this->host->id;
                }

                $this->object = IcingaServiceSet::load($params, $db);
            } else {
                parent::loadObject();
            }
        }

        return $this->object;
    }
}
