<?php

// TODO: Check whether this can be removed
namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\QuickForm;

class IcingaDeleteObjectForm extends QuickForm
{
    protected $object;

    public function setup()
    {
        $this->submitLabel = sprintf(
            $this->translate('YES, please delete "%s"'),
            $this->object->object_name
        );

    }

    public function onSuccess()
    {
        $object = $this->object;
        $msg = sprintf(
            'The %s "%s" has been deleted',
            $object->getShortTableName(),
            $object->object_name
        );

        if ($object->delete()) {
            $this->redirectOnSuccess($msg);
        } else {
            $this->redirectOnFailure($msg);
        }
    }

    public function setObject(IcingaObject $object)
    {
        $this->object = $object;
        return $this;
    }
}
