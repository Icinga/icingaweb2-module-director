<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfig;

abstract class IcingaObjectGroup extends IcingaObject
{
    // TODO: re-enable when used
    protected $supportsImports = false;

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
        'display_name'          => null,
    );

    /**
     * Render groups without extra object_types
     *
     * @param IcingaConfig $config
     */
    public function renderToConfig(IcingaConfig $config)
    {
        if ($this->isDisabled() || $this->isExternal()) {
            return;
        }

        $type = $this->getShortTableName();

        $filename = strtolower($type) . 's';

        $config->configFile(
            'zones.d/' . $this->getRenderingZone($config) . '/' . $filename
        )->addObject($this);
    }

    public function getRenderingZone(IcingaConfig $config = null)
    {
        return $this->connection->getDefaultGlobalZoneName();
    }

    /**
     * Will always be an apply, when it supports applies
     *
     * @return bool
     */
    public function isApplyRule()
    {
        return $this->supportsApplyRules();
    }

    /**
     * No extra object types
     *
     * @return string
     */
    protected function getObjectTypeName()
    {
        return 'object';
    }

}
