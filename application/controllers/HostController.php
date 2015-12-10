<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectController;

class HostController extends ObjectController
{
    public function init()
    {
        parent::init();
        if ($this->object) {
            $this->getTabs()->add('services', array(
                'url'       => 'director/host/services',
                'urlParams' => array('name' => $this->object->object_name),
                'label'     => 'Services'
            ));
        }
    }

    public function servicesAction()
    {
        $this->getTabs()->activate('services');
        $this->view->title = $this->translate('Services');
        $this->view->table = $this->loadTable('IcingaService')->enforceFilter('host_id', $this->object->id)->setConnection($this->db());
        $this->render('objects/table', null, true);
    }
}
