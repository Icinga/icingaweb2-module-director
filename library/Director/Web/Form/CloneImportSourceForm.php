<?php

namespace Icinga\Module\Director\Web\Form;

use ipl\Html\Form;
use ipl\Html\FormDecorator\DdDtDecorator;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Url;
use Icinga\Module\Director\Objects\ImportSource;

class CloneImportSourceForm extends Form
{
    use TranslationHelper;

    /** @var ImportSource */
    protected $source;

    /** @var ImportSource|null */
    protected $newSource;

    public function __construct(ImportSource $source)
    {
        $this->setDefaultElementDecorator(new DdDtDecorator());
        $this->source = $source;
    }

    protected function assemble()
    {
        $this->addElement('text', 'source_name', [
            'label' => $this->translate('New name'),
            'value' => $this->source->get('source_name'),
        ]);
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Clone')
        ]);
    }

    /**
     * @return \Icinga\Module\Director\Db
     */
    protected function getTargetDb()
    {
        return $this->source->getConnection();
    }

    /**
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function onSuccess()
    {
        $export = $this->source->export();
        $newName = $this->getElement('source_name')->getValue();
        $export->source_name = $newName;
        unset($export->originalId);

        if (ImportSource::existsWithName($newName, $this->source->getConnection())) {
            $this->getElement('source_name')->addMessage('Name already exists');
        }
        $this->newSource = ImportSource::import($export, $this->getTargetDb());
        $this->newSource->store();
        $this->redirectOnSuccess();
    }

    public function getSuccessUrl()
    {
        if ($this->newSource === null) {
            return parent::getSuccessUrl();
        } else {
            return Url::fromPath('director/importsource', ['id' => $this->newSource->get('id')]);
        }
    }
}
