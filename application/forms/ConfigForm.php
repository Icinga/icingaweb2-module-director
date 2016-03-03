<?php

namespace Icinga\Module\Director\Forms;

use Exception;
use Icinga\Application\Config;
use Icinga\Data\ResourceFactory;
use Icinga\Module\Director\Web\Form\QuickForm;

class ConfigForm extends QuickForm
{
    protected $config;

    public function setup()
    {
        $config = $this->config();
        $resources = $this->enumResources();

        $this->addHtml(sprintf('<h3>%s</h3>', $this->translate('Database backend')));
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

        $this->setSubmitLabel($this->translate('Store configuration'));
    }

    protected function onSetup()
    {
        if ($this->hasBeenSubmitted()) {
            // Do not hinder the form from being stored
            return;
        }

        if ($this->hasBeenSent() && $this->isValidPartial($this->getRequest()->getPost())) {
            $resourceName = $this->getValue('resource');
        } else {
            $resourceName = $this->config()->get('db', 'resource');
        }

        if ($resourceName) {
            $resourceConfig = ResourceFactory::getResourceConfig($resourceName);
            if (! isset($resourceConfig->charset)
                || $resourceConfig->charset !== 'utf8'
            ) {
                $this->getElement('resource')
                    ->addError('Please change the encoding for the director database to utf8')
                    ->removeDecorator('description');
            }

            $resource = ResourceFactory::create($resourceName);
            $db = $resource->getDbAdapter();

            try {
                $query = $db->select()->from('director_schema_migration', 'COUNT(*)');
                $db->fetchOne($query);

                if (! $this->hasBeenSent()) {
                    $hint = $this->translate(
                        'Configuration looks good, you should be ready to %s Icinga Director'
                    );
                    $link = $this->getView()->qlink(
                        $this->translate('start using'),
                        'director',
                        null,
                        array('data-base-target' => '_main')
                    );
                    $this->addHtmlHint(sprintf($hint, $link));
                }

            } catch (Exception $e) {
                $this->getElement('resource')
                    ->addError('Could not fetch: ' . $e->getMessage())
                    ->removeDecorator('description');

                $hint = $this->translate(
                    'Please make sure that your database grants enough permissions'
                    . ' and that you deployed the correct %s.'
                );
                $link = $this->getView()->qlink(
                    $this->translate('database schema'),
                    'director/schema/' . $resource->getDbType(),
                    null,
                    array('data-base-target' => '_next')
                );
                $this->addHtmlHint(sprintf($hint, $link));
            }
        }
    }

    public function setModuleConfig(Config $config)
    {
        $this->config = $config;
        return $this;
    }

    public function onSuccess()
    {
        $config = $this->config();
        $value = $this->getValue('resource');

        $config->setSection('db', array('resource' => $value));

        try {
            $config->saveIni();
            $this->redirectOnSuccess($this->translate('Configuration has been stored'));
        } catch (Exception $e) {
            $this->getElement('resource')->addError(
                sprintf(
                    $this->translate('Unable to store the configuration to "%s"'),
                    $config->getConfigFile()
                )
            )->removeDecorator('description');
            $this->addHtmlHint(
                '<pre>' . $config . '</pre>'
            );
        }
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
            if ($resource->type === 'db' && in_array($resource->db, $allowed)) {
                $resources[$name] = $name;
            }
        }

        return $resources;
    }
}
