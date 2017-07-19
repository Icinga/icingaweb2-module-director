<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Web\Form\DirectorForm;

class IcingaGenerateApiKeyForm extends DirectorForm
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
        if ($this->host->getProperty('api_key')) {
            $label = $this->translate('Regenerate Self Service API key');
        } else {
            $label = $this->translate('Generate Self Service API key');
        }

        $this->addStandaloneSubmitButton(sprintf(
            $label,
            $this->host->getObjectName()
        ));
    }

    public function onSuccess()
    {
        $host = $this->host;
        $host->generateApiKey();
        $host->store();
        $this->redirectOnSuccess(sprintf($this->translate(
            'A new Self Service API key for %s has been generated'
        ), $host->getObjectName()));
    }
}
