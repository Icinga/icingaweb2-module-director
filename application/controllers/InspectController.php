<?php

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Core\RestApiClient;
use Icinga\Module\Director\Core\CoreApi;

class Director_InspectController extends ActionController
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
            $this->view->objects = $this->api()->getObjects(
                $typeName,
                $type->plural_name,
                array_keys((array) $type->fields)
            );
        }
    }

    protected function api()
    {
        $apiconfig = $this->Config()->getSection('api');
        $client = new RestApiClient($apiconfig->get('address'), $apiconfig->get('port'));
        $client->setCredentials($apiconfig->get('username'), $apiconfig->get('password'));
        $api = new CoreApi($client);
        return $api;
    }
}
