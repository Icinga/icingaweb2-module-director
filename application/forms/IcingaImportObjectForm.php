<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\QuickForm;

class IcingaImportObjectForm extends QuickForm
{
    /** @var IcingaObject */
    protected $object;

    public function setup()
    {
        $this->addNote($this->translate(
            "Importing an object means that its type will change from"
            . ' "external" to "object". That way it will make part of the'
            . ' next deployment. So in case you imported this object from'
            . ' your Icinga node make sure to remove it from your local'
            . ' configuration before issueing the next deployment. In case'
            . ' of a conflict nothing bad will happen, just your config'
            . " won't deploy."
        ));

        $this->submitLabel = sprintf(
            $this->translate('Import external "%s"'),
            $this->object->object_name
        );
    }

    public function onSuccess()
    {
        $object = $this->object;
        if ($object->set('object_type', 'object')->store()) {
            $this->redirectOnSuccess(sprintf(
                $this->translate('%s "%s" has been imported"'),
                $object->getShortTableName(),
                $object->getObjectName()
            ));
        } else {
            $this->addError(sprintf(
                $this->translate('Failed to import %s "%s"'),
                $object->getShortTableName(),
                $object->getObjectName()
            ));
        }
    }

    public function setObject(IcingaObject $object)
    {
        $this->object = $object;
        return $this;
    }
}
