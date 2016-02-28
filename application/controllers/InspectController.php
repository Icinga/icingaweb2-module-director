<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;

class InspectController extends ActionController
{
    public function typesAction()
    {
        $api = $this->api();
        $params = array('name' => $this->view->endpoint);
        $this->getTabs()->add('modify', array(
            'url'       => 'director/endpoint',
            'urlParams' => $params,
            'label'     => $this->translate('Endpoint')
        ))->add('render', array(
            'url'       => 'director/endpoint/render',
            'urlParams' => $params,
            'label'     => $this->translate('Preview'),
        ))->add('history', array(
            'url'       => 'director/endpoint/history',
            'urlParams' => $params,
            'label'     => $this->translate('History')
        ))->add('inspect', array(
            'url'       => $this->getRequest()->getUrl(),
            'label'     => $this->translate('Inspect')
        ))->activate('inspect');

        $this->view->title = sprintf(
            $this->translate('Icinga2 Objects: %s'),
            $this->view->endpoint
        );
        $types = $api->getTypes();
        $rootNodes = array();
        foreach ($types as $name => $type) {
            if (property_exists($type, 'base')) {
                $base = $type->base;
                if (! property_exists($types[$base], 'children')) {
                    $types[$base]->children = array();
                }

                $types[$base]->children[$name] = $type;
            } else {
                $rootNodes[$name] = $type;
            }
        }
        $this->view->types = $rootNodes;
    }

    public function typeAction()
    {
        $typeName = $this->params->get('name');
        $this->view->title = sprintf(
            $this->translate('Object type "%s"'),
            $typeName
        );
        $this->view->type = $type = $this->api()->getType($typeName);
        if ($type->abstract) {
            return;
        }

        if (! empty($type->fields)) {
            $this->view->objects = $this->api()->listObjects(
                $typeName,
                $type->plural_name
            );
        }
    }

    public function objectAction()
    {
        $this->view->object = $this->api()->getObject(
            $this->params->get('name'),
            $this->params->get('plural')
        );
    }

    public function commandsAction()
    {
        $db = $this->db();
        echo '<pre>';
        foreach ($this->api()->setDb($db)->getCheckCommandObjects() as $object) {
            if (! $object::exists($object->object_name, $db)) {
                // var_dump($object->store());
                echo $object;
            }
        }
        echo '</pre>';
        exit;
    }

    public function zonesAction()
    {
        $db = $this->db();
        echo '<pre>';
        foreach ($this->api()->setDb($db)->getZoneObjects() as $zone) {
            if (! $zone::exists($zone->object_name, $db)) {
                // var_dump($zone->store());
                echo $zone;
            }
        }
        echo '</pre>';
        exit;
    }

    public function statusAction()
    {
        $this->view->status = $status = $this->api()->getStatus();
        print_r($status);
        exit;
    }

    protected function api($endpointName = null)
    {
        $this->view->endpoint = $this->params->get('endpoint');
        if ($this->view->endpoint === null) {
            $this->view->endpoint = $this->db()->getDeploymentEndpointName();
        }

        return parent::api($this->view->endpoint);
    }
}
