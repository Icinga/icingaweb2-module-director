<?php

namespace Icinga\Module\Director\Import;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class ImportSourceCoreApi extends ImportSourceHook
{
    protected $connection;

    protected $db;

    protected $api;

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
        if (empty($res)) {
            return array('object_name');
        }

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
            'multiOptions' => $form->optionalEnum(self::enumObjectTypes($form))
        ));
    }

    protected static function enumObjectTypes($form)
    {
        $types = array(
            'CheckCommand'        => $form->translate('Check Commands'),
            'NotificationCommand' => $form->translate('Notification Commands'),
            'Endpoint'            => $form->translate('Endpoints'),
            'Host'                => $form->translate('Hosts'),
            'HostGroup'           => $form->translate('Hostgroups'),
            'User'                => $form->translate('Users'),
            'UserGroup'           => $form->translate('Usergroups'),
            'Zone'                => $form->translate('Zones'),
        );

        asort($types);
        return $types;
    }

    protected function api()
    {
        if ($this->api === null) {
            $endpoint = $this->db()->getDeploymentEndpoint();
            $this->api = $endpoint->api()->setDb($this->db());
        }

        return $this->api;
    }

    protected function db()
    {
        if ($this->db === null) {
            $resourceName = Config::module('director')->get('db', 'resource');
            if ($resourceName) {
                $this->db = Db::fromResourceName($resourceName);
            }
        }

        return $this->db;
    }
}
