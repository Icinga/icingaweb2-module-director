<?php

use Icinga\Module\Director\ActionController;

use Icinga\Forms\ConfigForm;
use Icinga\Data\ResourceFactory;

class Director_SettingsController extends ActionController
{
    public function indexAction()
    {
        $this->view->tabs = $this->Module()->getConfigTabs()->activate('config');

        $resource = $this->Config()->get('db', 'resource');

        $form = new ConfigForm();

        $form->setIniConfig($this->Config('config'));
        $form->addElement('select', 'resource', array(
            'required'      => true,
            'label'         => $this->translate('DB Resource'),
            'multiOptions'  => array(null => $this->translate('- please choose -')) + $this->getResources(),
            'value'         => $resource
        ));
        $form->setSubmitLabel($this->translate('Save'));

        $form->setOnSuccess(function($form) {
            /** @var $form ConfigForm */
            $this->Config('config')->setSection('db', array(
                'resource' => $form->getValue('resource')
            ));
            $form->save();
        });

        $form->handleRequest();

        $this->view->form = $form;
    }

    public function getResources()
    {
        $resources = array();
        foreach (ResourceFactory::getResourceConfigs() as $name => $resource) {
            if ($resource->type === 'db') {
                $resources['ido'][$name] = $name;
            }
        }
        return $resources;
    }
}
