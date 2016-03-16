<?php

// TODO: Check whether this can be removed
namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\QuickForm;

class RestoreObjectForm extends QuickForm
{
    protected $db;

    protected $object;

    public function setup()
    {
        $this->submitLabel = $this->translate('Restore former object');
    }

    protected function addSubmitButtonIfSet()
    {
        $res = parent::addSubmitButtonIfSet();
        $this->getDisplayGroup('buttons')->setDecorators(array('FormElements'));
        return $res;
    }

    public function onSuccess()
    {
        $object = $this->object;
        $name = $object->object_name;
        $db = $this->db;
        $msg = $this->translate('Object has been restored');

        // TODO: service -> multi-key
        if ($object::exists($name, $db)) {
            $object::load($name, $db)->replaceWith($object)->store();
        } else {
            $object->store($db);
        }

        $this->redirectOnSuccess($msg);
    }

    public function setDb($db)
    {
        $this->db = $db;
        return $this;
    }

    public function setObject(IcingaObject $object)
    {
        $this->object = $object;
        return $this;
    }
}
