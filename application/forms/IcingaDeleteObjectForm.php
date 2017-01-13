<?php

// TODO: Check whether this can be removed
namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\QuickForm;

class IcingaDeleteObjectForm extends QuickForm
{
    /** @var  IcingaObject */
    protected $object;

    public function setup()
    {
        $this->submitLabel = sprintf(
            $this->translate('YES, please delete "%s"'),
            $this->object->getObjectName()
        );
    }

    public function onSuccess()
    {
        $object = $this->object;
        $msg = sprintf(
            'The %s "%s" has been deleted',
            $object->getShortTableName(),
            $object->getObjectName()
        );

        if ($object->delete()) {
            $this->redirectOnSuccess($msg);
        }
    }

    public function setObject(IcingaObject $object)
    {
        $this->object = $object;
        return $this;
    }
}
