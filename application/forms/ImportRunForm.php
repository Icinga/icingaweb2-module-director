<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Web\Form\DirectorForm;

class ImportRunForm extends DirectorForm
{
    /** @var  ImportSource */
    protected $source;

    public function setImportSource(ImportSource $source)
    {
        $this->source = $source;
        return $this;
    }

    public function setup()
    {
        $this->submitLabel = false;
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Trigger Import Run'),
            'decorators' => ['ViewHelper']
        ]);
    }

    public function onSuccess()
    {
        $source = $this->source;
        if ($source->runImport()) {
            $this->setSuccessMessage(
                $this->translate('Imported new data from this Import Source')
            );
        } else {
            $this->setSuccessMessage(
                $this->translate(
                    'Nothing to do, data provided by this Import Source'
                    . " didn't change since the last import run"
                )
            );
        }

        if ($source->get('import_state') === 'failing') {
            $this->addError($this->translate('Triggering this Import Source failed'));
        } else {
            parent::onSuccess();
        }
    }
}
