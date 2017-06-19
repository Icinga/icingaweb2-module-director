<?php

namespace Icinga\Module\Director\Web\Controller\Extension;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Restriction\HostgroupRestriction;
use Icinga\Module\Director\Restriction\ObjectRestriction;

trait ObjectRestrictions
{
    /** @var ObjectRestriction[] */
    private $objectRestrictions;

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
        return [
            new HostgroupRestriction($db, $auth)
        ];
    }

    public function allowsObject(IcingaObject $object)
    {
        foreach ($this->getObjectRestrictions() as $restriction) {
            if (! $restriction->allows($object)) {
                return false;
            }
        }

        return true;
    }
}
