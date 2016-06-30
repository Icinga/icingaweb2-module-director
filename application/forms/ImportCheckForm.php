<?php

// TODO: Check whether this can be removed
namespace Icinga\Module\Director\Forms;

use Exception;
use Icinga\Module\Director\Import\Import;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Web\Form\QuickForm;

class ImportCheckForm extends QuickForm
{
    protected $source;

    public function setImportSource(ImportSource $source)
    {
        $this->source = $source;
        return $this;
    }

    public function setup()
    {
        $this->submitLabel = false;
        $this->addElement('submit', 'submit', array(
            'label' => $this->translate('Check for changes'),
            'decorators' => array('ViewHelper')
        ));
    }

    public function onSuccess()
    {
        $source = $this->source;
        if ($source->checkForChanges()) {
            $this->setSuccessMessage(
                $this->translate('This Import Source provides modified data')
            );

        } else {
            $this->setSuccessMessage(
                $this->translate(
                    'Nothing to do, data provided by this Import Source'
                    . " didn't change since the last import run"
                )
            );
        }

        if ($source->import_state === 'failing') {
            $this->addError($this->translate('Checking this Import Source failed'));
        } else {
            parent::onSuccess();
        }
    }
}
