<?php

namespace Icinga\Module\Director\Forms;

use Exception;
use Icinga\Application\Config;
use Icinga\Data\ResourceFactory;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Migrations;
use Icinga\Module\Director\Objects\IcingaEndpoint;
use Icinga\Module\Director\KickstartHelper;
use Icinga\Module\Director\Web\Form\DirectorForm;
use Icinga\Module\Director\Web\Form\QuickForm;

class KickstartForm extends DirectorForm
{
    private $config;

    private $storeConfigLabel;

    private $createDbLabel;

    private $migrateDbLabel;

    /** @var IcingaEndpoint */
    private $endpoint;

    public function setup()
    {
        $this->storeConfigLabel = $this->translate('Store configuration');
        $this->createDbLabel    = $this->translate('Create database schema');
        $this->migrateDbLabel   = $this->translate('Apply schema migrations');

        $this->addResourceConfigElements();
        $this->addResourceDisplayGroup();

        if (!$this->config()->get('db', 'resource')
            || ($this->config()->get('db', 'resource') !== $this->getResourceName())) {
            return;
        }

        if (!$this->migrations()->hasSchema()) {
            $this->addHtmlHint($this->translate(
                'No database schema has been created yet'
            ), array('name' => 'HINT_schema'));

            $this->addResourceDisplayGroup();
            $this->setSubmitLabel($this->createDbLabel);
            return;
        }

        if ($this->migrations()->hasPendingMigrations()) {
            $this->addHtmlHint($this->translate(
                'There are pending database migrations'
            ), array('name' => 'HINT_schema'));

            $this->addResourceDisplayGroup();
            $this->setSubmitLabel($this->migrateDbLabel);
            return;
        }

        if (! $this->endpoint && $this->getDb()->hasDeploymentEndpoint()) {
            $hint = sprintf($this->translate(
                'Your database looks good, you are ready to %s'
            ), $this->getView()->qlink(
                'start working with the Icinga Director',
                'director',
                null,
                array('data-base-target' => '_main')
            ));

            $this->addHtmlHint($hint, array('name' => 'HINT_ready'));
            $this->getDisplayGroup('config')->addElements(
                array($this->getElement('HINT_ready'))
            );

            return;
        }

        $this->addResourceDisplayGroup();

        $this->addHtmlHint(
            $this->translate(
                'Your installation of Icinga Director has not yet been prepared for'
                . ' deployments. This kickstart wizard will assist you with setting'
                . ' up the connection to your Icinga 2 server.'
            ),
            array('name' => 'HINT_kickstart')
            // http://docs.icinga.com/icinga2/latest/doc/module/icinga2/chapter/object-types#objecttype-apilistener
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
            'label'       => $this->translate('Password'),
            'description' => $this->translate(
                'The corresponding password'
            ),
            'required'    => true,
        ));

        if ($ep = $this->endpoint) {
            $user = $ep->getApiUser();
            $this->setDefaults(array(
                'endpoint' => $ep->get('object_name'),
                'host'     => $ep->get('host'),
                'port'     => $ep->get('port'),
                'username' => $user->get('object_name'),
                'password' => $user->get('password'),
            ));

            if (! empty($user->password)) {
                $this->getElement('password')->setAttrib(
                    'placeholder',
                    '(use stored password)'
                )->setRequired(false);
            }
        }

        $this->addKickstartDisplayGroup();
        $this->setSubmitLabel($this->translate('Run import'));
    }

    protected function onSetup()
    {
        if ($this->hasBeenSubmitted()) {
            // Do not hinder the form from being stored
            return;
        }
        if ($resourceName = $this->getResourceName()) {
            $resourceConfig = ResourceFactory::getResourceConfig($resourceName);
            if (! isset($resourceConfig->charset)
                || ! in_array($resourceConfig->charset, array('utf8', 'utf8mb4'))
            ) {
                $this->getElement('resource')
                    ->addError('Please change the encoding for the director database to utf8');
            }

            $resource = $this->getResource();
            $db = $resource->getDbAdapter();

            try {
                $db->fetchOne('SELECT 1');
            } catch (Exception $e) {
                $this->getElement('resource')
                    ->addError('Could not connect to database: ' . $e->getMessage());

                $hint = $this->translate(
                    'Please make sure that your database exists and your user has'
                    . ' been granted enough permissions'
                );

                $this->addHtmlHint($hint, array('name' => 'HINT_db_perms'));
            }
        }
    }

    protected function addResourceConfigElements()
    {
        $config = $this->config();
        $resources = $this->enumResources();

        if (!$this->getResourceName()) {
            $this->addHtmlHint($this->translate(
                'No database resource has been configured yet. Please choose a'
                . ' resource to complete your config'
            ), array('name' => 'HINT_no_resource'));
        }

        $this->addElement('select', 'resource', array(
            'required'      => true,
            'label'         => $this->translate('DB Resource'),
            'multiOptions'  => $this->optionalEnum($resources),
            'class'         => 'autosubmit',
            'value'         => $config->get('db', 'resource')
        ));

        if (empty($resources)) {
            $this->getElement('resource')->addError(
                $this->translate('This has to be a MySQL or PostgreSQL database')
            );

            $hint = $this->translate('Please click %s to create new DB resources');
            $link = $this->getView()->qlink(
                $this->translate('here'),
                'config/resource',
                null,
                array('data-base-target' => '_main')
            );
            $this->addHtmlHint(sprintf($hint, $link));
        }

        $this->setSubmitLabel($this->storeConfigLabel);
    }

    protected function addResourceDisplayGroup()
    {
        $elements = array(
            'HINT_no_resource',
            'resource',
            'HINT_ready',
            'HINT_schema',
            'HINT_db_perms',
            'HINT_config_store'
        );

        $this->addDisplayGroup($elements, 'config', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order' => 40,
            'legend' => $this->translate('Database backend')
        ));
    }

    protected function addKickstartDisplayGroup()
    {
        $elements = array(
            'HINT_kickstart', 'endpoint', 'host', 'port', 'username', 'password'
        );

        $this->addDisplayGroup($elements, 'wizard', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order' => 60,
            'legend' => $this->translate('Kickstart Wizard')
        ));
    }

    protected function storeResourceConfig()
    {
        $config = $this->config();
        $value = $this->getValue('resource');

        $config->setSection('db', array('resource' => $value));

        try {
            $config->saveIni();
            $this->setSuccessMessage($this->translate('Configuration has been stored'));

            return true;
        } catch (Exception $e) {
            $this->getElement('resource')->addError(
                sprintf(
                    $this->translate(
                        'Unable to store the configuration to "%s". Please check'
                        . ' file permissions or manually store the content shown below'
                    ),
                    $config->getConfigFile()
                )
            );
            $this->addHtmlHint(
                '<pre>' . $config . '</pre>',
                array('name' => 'HINT_config_store')
            );

            $this->getDisplayGroup('config')->addElements(
                array($this->getElement('HINT_config_store'))
            );
            $this->removeElement('HINT_ready');

            return false;
        }
    }

    public function setEndpoint(IcingaEndpoint $endpoint)
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    public function onSuccess()
    {
        if ($this->getSubmitLabel() === $this->storeConfigLabel) {
            if ($this->storeResourceConfig()) {
                parent::onSuccess();
            } else {
                return;
            }
        }

        if ($this->getSubmitLabel() === $this->createDbLabel
            || $this->getSubmitLabel() === $this->migrateDbLabel) {
            $this->migrations()->applyPendingMigrations();
            parent::onSuccess();
        }

        $values = $this->getValues();
        if ($this->endpoint && empty($values['password'])) {
            $values['password'] = $this->endpoint->getApiUser()->password;
        }

        $kickstart = new KickstartHelper($this->getDb());
        unset($values['resource']);
        $kickstart->setConfig($values)->run();

        parent::onSuccess();
    }

    protected function getResourceName()
    {
        if ($this->hasBeenSent()) {
            $resource = $this->getSentValue('resource');
            $resources = $this->enumResources();
            if (in_array($resource, $resources)) {
                return $resource;
            } else {
                return null;
            }
        } else {
            return $this->config()->get('db', 'resource');
        }
    }

    public function getDb()
    {
        return Db::fromResourceName($this->getResourceName());
    }

    protected function getResource()
    {
        return ResourceFactory::create($this->getResourceName());
    }

    protected function migrations()
    {
        return new Migrations($this->getDb());
    }

    public function setModuleConfig(Config $config)
    {
        $this->config = $config;
        return $this;
    }

    protected function config()
    {
        if ($this->config === null) {
            $this->config = Config::module('director');
        }

        return $this->config;
    }

    protected function enumResources()
    {
        $resources = array();
        $allowed = array('mysql', 'pgsql');

        foreach (ResourceFactory::getResourceConfigs() as $name => $resource) {
            if ($resource->get('type') === 'db' && in_array($resource->get('db'), $allowed)) {
                $resources[$name] = $name;
            }
        }

        return $resources;
    }
}
