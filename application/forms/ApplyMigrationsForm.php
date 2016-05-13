<?php

namespace Icinga\Module\Director\Forms;

use Exception;
use Icinga\Module\Director\Db\Migrations;
use Icinga\Module\Director\Web\Form\QuickForm;

class ApplyMigrationsForm extends QuickForm
{
    protected $migrations;

    public function setup()
    {
        $this->setSubmitLabel($this->translate('Apply schema migrations'));
    }

    public function onSuccess()
    {
        try {
            $this->setSuccessMessage($this->translate(
                'Pending database schema migrations have successfully been applied'
            ));

            $this->migrations->applyPendingMigrations();
            parent::onSuccess();
        } catch (Exception $e) {
            $this->addError($e->getMessage());
        }
    }

    public function setMigrations(Migrations $migrations)
    {
        $this->migrations = $migrations;
        return $this;
    }
}
