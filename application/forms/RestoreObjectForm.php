<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Exception\NotFoundError;
use Icinga\Exception\NotImplementedError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\DirectorForm;

class RestoreObjectForm extends DirectorForm
{
    public const STATUS_RESTORED = 'restored';

    public const STATUS_RECREATED = 'recreated';

    public const STATUS_UNCHANGED = 'unchanged';

    public const STATUS_DELETED = 'deleted';

    /** @var IcingaObject */
    protected $object;

    /** @var IcingaObject|null used to find the live object, in case its identity changed since */
    protected $lookupObject;

    /** @var bool restoring a create activity means deleting the object again */
    protected $delete = false;

    public function setup()
    {
        $this->addSubmitButton($this->delete
            ? $this->translate('Delete this object')
            : $this->translate('Restore former object'));
    }

    public function onSuccess()
    {
        $result = static::restoreObject($this->object, $this->db, $this->delete, $this->lookupObject);
        $this->redirectOnSuccess($result['message']);
    }

    /**
     * Restore an old object snapshot onto the matching live object, re-create it
     * if it no longer exists, or delete it if $delete is set (used for create
     * activities, where there is no old state to go back to). Shared by the
     * single and bulk restore forms.
     *
     * $lookupObject is used to find the live object, pass the object's state right
     * after the activity got applied here. That matters for a rename, the live
     * object is now found under its new name, not the old one we're restoring
     *
     * @return array{status: string, message: string}
     */
    public static function restoreObject(
        IcingaObject $object,
        Db $db,
        bool $delete = false,
        ?IcingaObject $lookupObject = null
    ): array {
        $lookupObject = $lookupObject ?? $object;
        $lookupName = $lookupObject->getObjectName();
        $keyParams = $lookupObject->getKeyParams();

        if ($lookupObject->supportsApplyRules() && $lookupObject->get('object_type') === 'apply') {
            // TODO: not all apply should be considered unique by name + object_type
            $query = $db->getDbAdapter()
                ->select()
                ->from($lookupObject->getTableName())
                ->where('object_type = ?', 'apply')
                ->where('object_name = ?', $lookupName);

            $rules = $lookupObject::loadAll($db, $query);

            if (empty($rules)) {
                $existing = null;
            } elseif (count($rules) === 1) {
                $existing = current($rules);
            } else {
                // TODO: offer drop down?
                throw new NotImplementedError(
                    "Found multiple apply rule matching name '%s', can not restore!",
                    $lookupName
                );
            }
        } else {
            try {
                $existing = $lookupObject::load($keyParams, $db);
            } catch (NotFoundError $e) {
                $existing = null;
            }
        }

        if ($delete) {
            if ($existing === null) {
                return [
                    'status' => static::STATUS_UNCHANGED,
                    'message' => sprintf(mt('director', 'Nothing to do, "%s" does not exist'), $lookupName),
                ];
            }

            $type = $existing->get('object_type');
            $existing->delete();

            return [
                'status' => static::STATUS_DELETED,
                'message' => sprintf(mt('director', '%s "%s" has been deleted'), $type, $lookupName),
            ];
        }

        $name = $object->getObjectName();

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
                $existing->store();
                return [
                    'status' => static::STATUS_RESTORED,
                    'message' => sprintf(mt('director', '%s "%s" has been restored'), $typeObject, $name),
                ];
            }

            return [
                'status' => static::STATUS_UNCHANGED,
                'message' => sprintf(mt('director', 'Nothing to do, restore would not modify "%s"'), $name),
            ];
        }

        $object->store($db);

        return [
            'status' => static::STATUS_RECREATED,
            'message' => sprintf(mt('director', '%s "%s" has been re-created'), $object->get('object_type'), $name),
        ];
    }

    public function setObject(IcingaObject $object)
    {
        $this->object = $object;
        return $this;
    }

    public function setLookupObject(?IcingaObject $lookupObject)
    {
        $this->lookupObject = $lookupObject;

        return $this;
    }

    public function setDelete(bool $delete = true)
    {
        $this->delete = $delete;

        return $this;
    }
}
