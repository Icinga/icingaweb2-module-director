<?php

namespace Icinga\Module\Director\Forms;

use gipfl\Web\Form;
use Icinga\Module\Director\Data\Db\DbObjectStore;
use Icinga\Module\Director\Import\Sync;
use Icinga\Module\Director\Objects\SyncRule;
use ipl\I18n\Translation;

class SyncRunForm extends Form
{
    use Translation;

    protected $defaultDecoratorClass = null;

    /** @var ?string */
    protected $successMessage = null;

    /** @var SyncRule */
    protected $rule;

    /** @var DbObjectStore */
    protected $store;

    public function __construct(SyncRule $rule, DbObjectStore $store)
    {
        $this->rule = $rule;
        $this->store = $store;
    }

    public function assemble()
    {
        if ($this->store->getBranch()->isBranch()) {
            $label = sprintf($this->translate('Sync to Branch: %s'), $this->store->getBranch()->getName());
        } else {
            $label = $this->translate('Trigger this Sync');
        }
        $this->addElement('submit', 'submit', [
            'label' => $label,
        ]);
    }

    /**
     * @return string|null
     */
    public function getSuccessMessage()
    {
        return $this->successMessage;
    }

    public function onSuccess()
    {
        $sync = new Sync($this->rule, $this->store);
        if ($sync->hasModifications()) {
            if ($sync->apply()) {
                // and changed
                $this->successMessage = $this->translate(('Source has successfully been synchronized'));
            } else {
                $this->successMessage = $this->translate('Nothing changed, rule is in sync');
            }
        } else {
            // Used to be $rule->get('sync_state') === 'in-sync', $changed = $rule->applyChanges();
            $this->successMessage = $this->translate('Nothing to do, rule is in sync');
        }
    }
}
