<?php

namespace Icinga\Module\Director\Web\Form;

use gipfl\Web\Form;
use Icinga\Module\Director\Data\Exporter;
use Icinga\Module\Director\Data\ObjectImporter;
use Icinga\Module\Director\Db;
use ipl\Html\FormDecorator\DdDtDecorator;
use ipl\I18n\Translation;
use gipfl\IcingaWeb2\Url;
use Icinga\Module\Director\Objects\SyncRule;

class CloneSyncRuleForm extends Form
{
    use Translation;

    /** @var SyncRule */
    protected $rule;

    /** @var SyncRule|null */
    protected $newRule;

    public function __construct(SyncRule $rule)
    {
        $this->setDefaultElementDecorator(new DdDtDecorator());
        $this->rule = $rule;
    }

    protected function assemble()
    {
        $this->addElement('text', 'rule_name', [
            'label' => $this->translate('New name'),
            'value' => $this->rule->get('rule_name'),
        ]);
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Clone')
        ]);
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function onSuccess()
    {
        $db = $this->rule->getConnection();
        assert($db instanceof Db);
        $exporter = new Exporter($db);

        $export = $exporter->export($this->rule);
        $newName = $this->getValue('rule_name');
        $export->rule_name = $newName;
        unset($export->uuid);

        if (SyncRule::existsWithName($newName, $db)) {
            $this->getElement('rule_name')->addMessage('Name already exists');
        }
        $importer = new ObjectImporter($db);
        $this->newRule = $importer->import(SyncRule::class, $export);
        $this->newRule->store();
    }

    public function getSuccessUrl()
    {
        return Url::fromPath('director/syncrule', ['id' => $this->newRule->get('id')]);
    }
}
