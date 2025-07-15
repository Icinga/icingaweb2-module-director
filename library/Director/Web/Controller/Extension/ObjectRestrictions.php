<?php

namespace Icinga\Module\Director\Web\Controller\Extension;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Restriction\FilterByNameRestriction;
use Icinga\Module\Director\Restriction\HostgroupRestriction;
use Icinga\Module\Director\Restriction\ObjectRestriction;

trait ObjectRestrictions
{
    /** @var ObjectRestriction[] */
    private $objectRestrictions;

    /** @var IcingaObject */
    private $dummyRestrictedObject;

    /**
     * @return ObjectRestriction[]
     */
    public function getObjectRestrictions()
    {
        if ($this->objectRestrictions === null) {
            $this->objectRestrictions = $this->loadObjectRestrictions($this->db(), $this->Auth());
        }

        return $this->objectRestrictions;
    }

    /**
     * @return ObjectRestriction[]
     */
    protected function loadObjectRestrictions(Db $db, Auth $auth)
    {
        $objectType = $this->dummyRestrictedObject->getShortTableName();
        if (
            ($objectType === 'service' && $this->dummyRestrictedObject->isApplyRule())
            || $objectType === 'notification'
            || $objectType === 'service_set'
            || $objectType === 'scheduled_downtime'
        ) {
            if ($objectType === 'scheduled_downtime') {
                $objectType = 'scheduled-downtime';
            }

            return [new FilterByNameRestriction($db, $auth, $objectType)];
        }

        // If the object is host or host group load HostgroupRestriction
        return [new HostgroupRestriction($db, $auth)];
    }

    public function allowsObject(IcingaObject $object)
    {
        $this->dummyRestrictedObject = $object;
        foreach ($this->getObjectRestrictions() as $restriction) {
            if (! $restriction->allows($object)) {
                return false;
            }
        }

        return true;
    }
}
