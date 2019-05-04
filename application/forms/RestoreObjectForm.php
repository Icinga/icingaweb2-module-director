<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Exception\NotFoundError;
use Icinga\Exception\NotImplementedError;
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

        $keyParams = $object->getKeyParams();

        if ($object->supportsApplyRules() && $object->get('object_type') === 'apply') {
            // TODO: not all apply should be considered unique by name + object_type
            $query = $db->getDbAdapter()
                ->select()
                ->from($object->getTableName())
                ->where('object_type = ?', 'apply')
                ->where('object_name = ?', $name);

            $rules = $object::loadAll($db, $query);

            if (empty($rules)) {
                $existing = null;
            } elseif (count($rules) === 1) {
                $existing = current($rules);
            } else {
                // TODO: offer drop down?
                throw new NotImplementedError(
                    "Found multiple apply rule matching name '%s', can not restore!",
                    $name
                );
            }
        } else {
            try {
                $existing = $object::load($keyParams, $db);
            } catch (NotFoundError $e) {
                $existing = null;
            }
        }

        if ($existing !== null) {
            $typeExisting = $existing->get('object_type');
            $typeObject = $object->get('object_type');
            if ($typeExisting !== $typeObject) {
                // Not sure when that may occur
                throw new NotImplementedError(
                    'Found existing object has a mismatching object_type: %s != %s',
                    $typeExisting,
                    $typeObject
                );
            }

            $existing->replaceWith($object);

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
