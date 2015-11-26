<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;

class InspectController extends ActionController
{
    public function typesAction()
    {
        $this->view->title = $this->translate('Icinga2 object types');
        $api = $this->api();
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

    public function statusAction()
    {
        $this->view->status = $status = $this->api()->getStatus();
        print_r($status); exit;
    }
}
