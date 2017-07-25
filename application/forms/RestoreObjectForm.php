<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\DirectorForm;

class RestoreObjectForm extends DirectorForm
{
    /** @var IcingaObject */
    protected $object;

    public function setup()
    {
        $this->addSubmitButton($this->translate('Restore former object'));
    }

    public function onSuccess()
    {
        $object = $this->object;
        $name = $object->getObjectName();
        $db = $this->db;

        // TODO: service -> multi-key
        if ($object::exists($name, $db)) {
            $existing = $object::load($name, $db)->replaceWith($object);

            if ($existing->hasBeenModified()) {
                $msg = $this->translate('Object has been restored');
                $existing->store();
            } else {
                $msg = $this->translate(
                    'Nothing to do, restore would not modify the current object'
                );
            }
        } else {
            $msg = $this->translate('Object has been re-created');
            $object->store($db);
        }

        $this->redirectOnSuccess($msg);
    }

    public function setObject(IcingaObject $object)
    {
        $this->object = $object;
        return $this;
    }
}
