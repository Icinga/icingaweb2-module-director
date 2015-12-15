<?php

namespace Icinga\Module\Director\Import;

use Icinga\Application\Config;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Core\RestApiClient;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Web\Hook\ImportSourceHook;

class ImportSourceCoreApi extends ImportSourceHook
{
    protected $connection;

    public function fetchData()
    {
        $func = 'get' . $this->getSetting('object_type') . 'Objects';
        $objects = $this->api()->$func();
        $result = array();
        foreach ($objects as $object) {
            $result[] = $object->toPlainObject();
        }

        return $result;
    }

    public function listColumns()
    {
        $res = $this->fetchData();
        return array_keys((array) $res[0]);
    }

    public static function getDefaultKeyColumnName()
    {
        return 'object_name';
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'object_type', array(
            'label'    => 'Object type',
            'required' => true,
            'multiOptions' => $form->optionalEnum(array(
                'CheckCommand'  => 'Commands',
                'Endpoint' => 'Endpoints',
                'Zone'     => 'Zones',
            ))
        ));
    }

    protected function api()
    {
        $apiconfig = Config::module('director')->getSection('api');
        $client = new RestApiClient($apiconfig->get('address'), $apiconfig->get('port'));
        $client->setCredentials($apiconfig->get('username'), $apiconfig->get('password'));
        $api = new CoreApi($client);
        return $api;
    }
}
