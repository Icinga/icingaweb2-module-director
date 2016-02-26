<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\KickstartHelper;
use Icinga\Module\Director\Web\Form\QuickForm;

class KickstartForm extends QuickForm
{
    protected $db;

    public function setup()
    {
        $this->addHtmlHint(
            $this->translate(
                'Your installation of Icinga Director has not yet been prepared for deployments.'
                . ' This kickstart wizard will assist you with setting up the connection to your Icinga 2 server'
            )
        );

        $this->addElement('text', 'endpoint', array(
            'label'       => $this->translate('Endpoint Name'),
            'required'    => true,
        ));

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
        $kickstart = new KickstartHelper($this->db);
        $kickstart->setConfig($this->getValues())->run();
        parent::onSuccess();
    }

    public function setDb($db)
    {
        $this->db = $db;
        if ($this->object !== null) {
            $this->object->setConnection($db);
        }

        return $this;
    }
}
