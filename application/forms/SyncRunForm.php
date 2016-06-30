<?php

// TODO: Check whether this can be removed
namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Director\Web\Form\QuickForm;

class SyncRunForm extends QuickForm
{
    protected $rule;

    public function setSyncRule(SyncRule $rule)
    {
        $this->rule = $rule;
        return $this;
    }

    public function setup()
    {
        $this->submitLabel = false;
        $this->addElement('submit', 'submit', array(
            'label' => $this->translate('Trigger this Sync'),
            'decorators' => array('ViewHelper')
        ));
    }

    public function onSuccess()
    {
        $rule = $this->rule;
        $changed = $rule->applyChanges();

        if ($changed) {
            $runId = $rule->getCurrentSyncRunId();
            $this->setSuccessMessage(
                $this->translate(('Source has successfully been synchronized'))
            );
        } elseif ($rule->sync_state === 'in-sync') {
            $this->setSuccessMessage(
                $this->translate('Nothing changed, rule is in sync')
            );
        } else {
            $this->addError($this->translate('Synchronization failed'));
        }

        parent::onSuccess();
    }
}
