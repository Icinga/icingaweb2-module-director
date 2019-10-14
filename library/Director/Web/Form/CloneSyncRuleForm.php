<?php

namespace Icinga\Module\Director\Web\Form;

use ipl\Html\Form;
use ipl\Html\FormDecorator\DdDtDecorator;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Url;
use Icinga\Module\Director\Objects\SyncRule;

class CloneSyncRuleForm extends Form
{
    use TranslationHelper;

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
     * @return \Icinga\Module\Director\Db
     */
    protected function getTargetDb()
    {
        return $this->rule->getConnection();
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function onSuccess()
    {
        $export = $this->rule->export();
        $newName = $this->getValue('rule_name');
        $export->rule_name = $newName;
        unset($export->originalId);

        if (SyncRule::existsWithName($newName, $this->getTargetDb())) {
            $this->getElement('rule_name')->addMessage('Name already exists');
        }
        $this->newRule = SyncRule::import($export, $this->getTargetDb());
        $this->newRule->store();
    }

    public function getSuccessUrl()
    {
        if ($this->newRule === null) {
            return parent::getSuccessUrl();
        } else {
            return Url::fromPath('director/syncrule', ['id' => $this->newRule->get('id')]);
        }
    }
}
