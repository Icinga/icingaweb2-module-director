<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;

abstract class IcingaObjectGroup extends IcingaObject implements ExportInterface
{
    protected $supportsImports = true;

    protected $supportedInLegacy = true;

    protected $uuidColumn = 'uuid';

    protected $defaultProperties = [
        'id'            => null,
        'uuid'          => null,
        'object_name'   => null,
        'object_type'   => null,
        'disabled'      => 'n',
        'display_name'  => null,
        'assign_filter' => null,
    ];

    public function getUniqueIdentifier()
    {
        return $this->getObjectName();
    }

    protected function prefersGlobalZone()
    {
        return true;
    }

    protected function memberShipShouldBeRefreshed(): bool
    {
        if ($this->hasBeenLoadedFromDb()) {
            if (!array_key_exists('assign_filter', $this->getModifiedProperties())) {
                return false;
            }
        } else {
            if ($this->get('assign_filter') === null) {
                return false;
            }
        }

        return true;
    }

    protected function notifyResolvers()
    {
        if ($this->memberShipShouldBeRefreshed()) {
            $resolver = $this->getMemberShipResolver();
            $resolver->addGroup($this);
            $resolver->refreshDb();
        }

        return $this;
    }

    protected function getMemberShipResolver()
    {
        return null;
    }
}
