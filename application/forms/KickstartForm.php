<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaApiUser;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Core\RestApiClient;
use Icinga\Module\Director\Web\Form\QuickForm;

class KickstartForm extends QuickForm
{
    protected $db;

    protected $apiUser;

    public function setup()
    {
        $this->addHtmlHint(
            $this->translate(
                'Your installation of Icinga Director has not yet been prepared for deployments. This kickstart wizard will assist you with setting up the connection to your Icinga 2 server'
            )
        );
        // TODO: distinct endpoint name / host
        $this->addElement('text', 'host', array(
            'label'       => $this->translate('Icinga Host'),
            'description' => $this->translate('IP address / hostname of remote node'),
            'required'    => true,
        ));

        $this->addElement('text', 'port', array(
            'label'       => $this->translate('Port'),
            'value'       => '5665',
            'description' => $this->translate('The port your '),
            'required'    => true,
        ));

        $this->addElement('text', 'username', array(
            'label'    => $this->translate('API user'),
            'required' => true,
        ));

        $this->addElement('password', 'password', array(
            'label'    => $this->translate('Password'),
            'required' => true,
        ));
    }

    public function onSuccess()
    {
        $this->importZones();
        $this->importEndpoints();
        $this->apiUser()->store();
        parent::onSuccess();
    }

    protected function apiUser()
    {
        if ($this->apiUser === null) {
            $this->apiUser = IcingaApiUser::create(array(
                'object_name' => $this->getValue('username'),
                'object_type' => 'external_object',
                'password'    => $this->getValue('password')
            ), $this->db);
        }

        return $this->apiUser;
    }

    protected function importZones()
    {
        $db = $this->db;
        foreach ($this->api()->setDb($db)->getZoneObjects() as $object) {
            if (! $object::exists($object->object_name, $db)) {
                $object->store();
            }
        }
    }

    protected function importEndpoints()
    {
        $db = $this->db;
        $master = $this->getValue('host');

        foreach ($this->api()->setDb($db)->getEndpointObjects() as $object) {
            if ($object->object_name === 'master') {
                $this->apiUser()->store();
                $object->apiuser = $this->apiUser()->object_name;
            }

            if (! $object::exists($object->object_name, $db)) {
                $object->store();
            }
        }
    }

    public function setDb($db)
    {
        $this->db = $db;
        if ($this->object !== null) {
            $this->object->setConnection($db);
        }

        return $this;
    }

    protected function api()
    {
        $client = new RestApiClient($this->getValue('host'), $this->getValue('port'));
        $client->setCredentials($this->apiUser()->object_name, $this->apiUser()->password);
        $api = new CoreApi($client);
        return $api;
    }
}
