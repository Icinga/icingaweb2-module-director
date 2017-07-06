<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\IcingaHostSelfServiceForm;
use Icinga\Module\Director\Web\Controller\ActionController;
use ipl\Html\Html;

class SelfServiceController extends ActionController
{
    protected $isApified = true;

    protected $requiresAuthentication = false;

    protected function assertApiPermission()
    {
        // no permission required, we'll check the API key
    }

    protected function checkDirectorPermissions()
    {
    }

    public function registerHostAction()
    {
        $form = IcingaHostSelfServiceForm::create($this->db());
        if ($key = $this->params->get('key')) {
            $form->loadTemplateWithApiKey($key);
        }
        if ($name = $this->params->get('name')) {
            $form->setHostName($name);
        }

        $form->handleRequest();

        if ($this->getRequest()->isApiRequest()) {
            if ($newKey = $form->getHostApiKey()) {
                $this->sendJson($this->getResponse(), $newKey);
            } else {
                $error = implode('; ', $form->getErrorMessages());
                if ($error === '') {
                    foreach ($form->getErrors() as $elName => $errors) {
                        if (in_array('isEmpty', $errors)) {
                            $this->sendJsonError(
                                $this->getResponse(),
                                sprintf("%s is required", $elName)
                            );
                            return;
                        } else {
                            $this->sendJsonError($this->getResponse(), 'An unknown error ocurred');
                        }
                    }
                } else {
                    $this->sendJsonError($this->getResponse(), $error);
                }
            }
            return;
        }

        $this->addSingleTab($this->translate('Self Service'))
              ->addTitle($this->translate('Self Service - Host Registration'))
              ->content()->add(Html::p($this->translate(
                'In case an Icinga Admin provided you with a self service API'
                . ' token, this is where you can register new hosts'
                )))
            ->add($form);
    }
}
