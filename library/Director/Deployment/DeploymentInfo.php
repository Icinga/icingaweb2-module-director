<?php

namespace Icinga\Module\Director\Deployment;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;

class DeploymentInfo
{
    /** @var IcingaObject */
    protected $object;

    protected $db;

    /** @var int */
    protected $totalChanges;

    /** @var int */
    protected $objectChanges;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function setObject(IcingaObject $object)
    {
        $this->object = $object;
        return $this;
    }

    public function getTotalChanges()
    {
        if ($this->totalChanges === null) {
            $this->totalChanges = $this->db->countActivitiesSinceLastDeployedConfig();
        }

        return $this->totalChanges;
    }

    public function getSingleObjectChanges()
    {
        if ($this->objectChanges === null) {
            if ($this->object === null) {
                $this->objectChanges = 0;
            } else {
                $this->objectChanges = $this->db
                    ->countActivitiesSinceLastDeployedConfig($this->object);
            }
        }

        return $this->objectChanges;
    }

    public function hasUndeployedChanges()
    {
        return $this->getSingleObjectChanges() > 0 && $this->getTotalChanges() > 0;
    }
}
