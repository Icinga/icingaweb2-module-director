<?php

namespace Icinga\Module\Director\Forms;

use Exception;
use Icinga\Module\Director\Db\Migrations;
use Icinga\Module\Director\Web\Form\DirectorForm;

class ApplyMigrationsForm extends DirectorForm
{
    /** @var  Migrations */
    protected $migrations;

    public function setup()
    {
        if ($this->migrations->hasSchema()) {
            $count = $this->migrations->countPendingMigrations();
            if ($count === 1) {
                $this->setSubmitLabel(
                    $this->translate('Apply a pending schema migration')
                );
            } else {
                $this->setSubmitLabel(
                    sprintf(
                        $this->translate('Apply %d pending schema migrations'),
                        $count
                    )
                );
            }
        } else {
            $this->setSubmitLabel($this->translate('Create schema'));
        }
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
