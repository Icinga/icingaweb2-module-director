<?php

namespace Icinga\Module\Director\Forms;

use Exception;
use Icinga\Module\Director\Db\Migrations;
use Icinga\Module\Director\KickstartHelper;
use Icinga\Module\Director\Web\Form\QuickForm;

class KickstartForm extends QuickForm
{
    protected $db;

    protected $createDbLabel;

    public function setup()
    {
        $this->createDbLabel = $this->translate('Create database schema');
        if (!$this->migrations()->hasSchema()) {

            $this->addHtmlHint($this->translate(
                'No database schema has been created yet'
            ));

            $this->setSubmitLabel($this->createDbLabel);
            return ;
        }

        $this->addHtmlHint(
            $this->translate(
                'Your installation of Icinga Director has not yet been prepared for deployments.'
                . ' This kickstart wizard will assist you with setting up the connection to your Icinga 2 server'
            )
        );

        $this->addElement('text', 'endpoint', array(
            'label'       => $this->translate('Endpoint Name'),
            'description' => $this->translate(
                'This is the name of the Endpoint object (and certificate name) you'
                . ' created for your ApiListener object. In case you are unsure what'
                . ' this means please make sure to read the documentation first'
            ),
            'required'    => true,
        ));

        $this->addElement('text', 'host', array(
            'label'       => $this->translate('Icinga Host'),
            'description' => $this->translate(
                'IP address / hostname of your Icinga node. Please note that this'
                . ' information will only be used for the very first connection to'
                . ' your Icinga instance. The Director then relies on a correctly'
                . ' configured Endpoint object. Correctly configures means that either'
                . ' it\'s name is resolvable or that it\'s host property contains'
                . ' either an IP address or a resolvable host name. Your Director must'
                . ' be able to reach this endpoint'
            ),
            'required'    => false,
        ));

        $this->addElement('text', 'port', array(
            'label'       => $this->translate('Port'),
            'value'       => '5665',
            'description' => $this->translate(
                'The port you are going to use. The default port 5665 will be used'
                . ' if none is set'
            ),
            'required'    => false,
        ));

        $this->addElement('text', 'username', array(
            'label'    => $this->translate('API user'),
            'description' => $this->translate(
                'Your Icinga 2 API username'
            ),
            'required' => true,
        ));

        $this->addElement('password', 'password', array(
            'label'    => $this->translate('Password'),
            'description' => $this->translate(
                'The corresponding password'
            ),
            'required' => true,
        ));
    }

    public function onSuccess()
    {
        try {
            if ($this->getSubmitLabel() === $this->createDbLabel) {
                $this->migrations()->applyPendingMigrations();
                return parent::onSuccess();
            }
            $kickstart = new KickstartHelper($this->db);
            $kickstart->setConfig($this->getValues())->run();
            parent::onSuccess();
        } catch (Exception $e) {
            $this->addError($e->getMessage());
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

    protected function migrations()
    {
        return new Migrations($this->db);
    }
}
