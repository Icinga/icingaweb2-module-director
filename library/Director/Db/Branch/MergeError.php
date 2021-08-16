<?php

namespace Icinga\Module\Director\Db\Branch;

use Exception;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Objects\IcingaObject;
use Ramsey\Uuid\UuidInterface;

abstract class MergeError extends Exception
{
    use TranslationHelper;

    /** @var ObjectModification */
    protected $modification;

    /** @var UuidInterface */
    protected $activityUuid;

    public function __construct(ObjectModification $modification, UuidInterface $activityUuid)
    {
        $this->modification = $modification;
        $this->activityUuid = $activityUuid;
        parent::__construct($this->prepareMessage());
    }

    abstract protected function prepareMessage();

    public function getObjectTypeName()
    {
        /** @var string|DbObject $class */
        $class = $this->getModification()->getClassName();
        $dummy = $class::create([]);
        if ($dummy instanceof IcingaObject) {
            return $dummy->getShortTableName();
        }

        return $dummy->getTableName();
    }

    public function getActivityUuid()
    {
        return $this->activityUuid;
    }

    public function getNiceObjectName()
    {
        $keyParams = $this->getModification()->getKeyParams();
        if (array_keys((array) $keyParams) === ['object_name']) {
            return $keyParams->object_name;
        }

        return json_encode($keyParams);
    }

    public function getModification()
    {
        return $this->modification;
    }
}
