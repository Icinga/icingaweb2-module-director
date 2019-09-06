<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\KickstartForm;
use Icinga\Module\Director\Forms\SelfServiceSettingsForm;
use Icinga\Module\Director\Settings;
use Icinga\Module\Director\Web\Controller\ActionController;
use dipl\Html\Html;

class SettingsController extends ActionController
{
    /**
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     */
    public function indexAction()
    {
        // Hint: this is for the module configuration tab, legacy code
        $this->view->tabs = $this->Module()
            ->getConfigTabs()
            ->activate('config');

        $this->view->form = KickstartForm::load()
            ->setModuleConfig($this->Config())
            ->handleRequest();
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\IcingaException
     */
    public function selfServiceAction()
    {
        $form = SelfServiceSettingsForm::create($this->db(), new Settings($this->db()));
        $form->handleRequest();

        $hint = $this->translate(
            'The Icinga Director Self Service API allows your Hosts to register'
            . ' themselves. This allows them to get their Icinga Agent configured,'
            . ' installed and upgraded in an automated way.'
        );

        $this->addSingleTab($this->translate('Self Service'))
            ->addTitle($this->translate('Self Service API - Global Settings'))
            ->content()->add(Html::tag('p', null, $hint))
            ->add($form);
    }
}
