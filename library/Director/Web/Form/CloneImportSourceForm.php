<?php

namespace Icinga\Module\Director\Web\Form;

use gipfl\Web\Form;
use Icinga\Module\Director\Data\Exporter;
use Icinga\Module\Director\Data\ObjectImporter;
use Icinga\Module\Director\Db;
use ipl\Html\FormDecorator\DdDtDecorator;
use ipl\I18n\Translation;
use gipfl\IcingaWeb2\Url;
use Icinga\Module\Director\Objects\ImportSource;

class CloneImportSourceForm extends Form
{
    use Translation;

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

    public function onSuccess()
    {
        $db = $this->source->getConnection();
        assert($db instanceof Db);
        $export = (new Exporter($db))->export($this->source);
        $newName = $this->getElement('source_name')->getValue();
        $export->source_name = $newName;
        unset($export->uuid);

        if (ImportSource::existsWithName($newName, $db)) {
            $this->getElement('source_name')->addMessage('Name already exists');
        }
        $importer = new ObjectImporter($db);
        $this->newSource = $importer->import(ImportSource::class, $export);
        $this->newSource->store();
    }

    public function getSuccessUrl()
    {
        return Url::fromPath('director/importsource', ['id' => $this->newSource->get('id')]);
    }
}
