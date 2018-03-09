<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Director\Web\Form\DirectorForm;

class SyncCheckForm extends DirectorForm
{
    /** @var SyncRule */
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
            'label' => $this->translate('Check for changes'),
            'decorators' => array('ViewHelper')
        ));
    }

    public function onSuccess()
    {
        if ($this->rule->checkForChanges()) {
            $this->notifySuccess(
                $this->translate(('This Sync Rule would apply new changes'))
            );
            $sum = array('create' => 0, 'modify' => 0, 'delete' => 0);

            // TODO: Preview them? Like "hosta, hostb and 4 more would be...
            foreach ($this->rule->getExpectedModifications() as $object) {
                if ($object->shouldBeRemoved()) {
                    $sum['delete']++;
                } elseif (! $object->hasBeenLoadedFromDb()) {
                    $sum['create']++;
                } elseif ($object->hasBeenModified()) {
                    $sum['modify']++;
                }
            }

            /**
            if ($sum['modify'] === 1) {
                $html .= $this->translate('One object would be modified'
            } elseif ($sum['modify'] > 1) {
            }
            */
            $html = '<pre>' . print_r($sum, 1) . '</pre>';

            $this->addHtml($html);
        } elseif ($this->rule->get('sync_state') === 'in-sync') {
            $this->setSuccessMessage(
                $this->translate('Nothing would change, this rule is still in sync')
            );
        } else {
            $this->addError($this->translate('Checking this sync rule failed'));
        }
    }
}
