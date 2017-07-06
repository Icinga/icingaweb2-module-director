<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\SelfServiceSettingsForm;
use Icinga\Module\Director\Settings;
use Icinga\Module\Director\Web\Controller\ActionController;
use ipl\Html\Html;

class SettingsController extends ActionController
{
    public function selfServiceAction()
    {
        $form = SelfServiceSettingsForm::create($this->db(), new Settings($this->db()));

        $hint = $this->translate(
            'The Icinga Director Self Service API allows your Hosts to register'
            . ' themselves. This allows them to get their Icinga Agent configured,'
            . ' installed and upgraded in an automated way.'
        );

        $this->addSingleTab($this->translate('Self Service'))
            ->addTitle($this->translate('Self Service API - Settings'))
            ->content()->add(Html::p($hint))
            ->add($form);
    }
}
