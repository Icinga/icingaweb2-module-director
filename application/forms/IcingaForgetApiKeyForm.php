<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Web\Form\DirectorForm;

class IcingaForgetApiKeyForm extends DirectorForm
{
    /** @var IcingaHost */
    protected $host;

    public function setHost(IcingaHost $host)
    {
        $this->host = $host;
        return $this;
    }

    public function setup()
    {
        $this->addStandaloneSubmitButton(sprintf(
            $this->translate('Drop Self Service API key'),
            $this->host->getObjectName()
        ));
    }

    public function onSuccess()
    {
        $this->host->set('api_key', null)->store();
        $this->redirectOnSuccess(sprintf($this->translate(
            'The Self Service API key for %s has been dropped'
        ), $this->host->getObjectName()));
    }
}
