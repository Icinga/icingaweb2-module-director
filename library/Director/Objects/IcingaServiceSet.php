<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\Objects\IcingaService;

class IcingaServiceSet extends IcingaObject
{
    protected $table = 'icinga_service_set';

    protected $defaultProperties = array(
        'id'                    => null,
        'host_id'               => null,
        'object_name'           => null,
        'object_type'           => null,
        'description'           => null,
    );

    protected $keyName = array('host_id', 'object_name');

    protected $supportsImports = true;

    protected $supportsCustomVars = true;

    protected $supportsApplyRules = true;

    protected $relations = array(
        'host' => 'IcingaHost',
    );

    protected $multiRelations = array(
        'service' => 'IcingaService',
    );

    public function isDisabled()
    {
        return false;
    }

    public function supportsAssignments()
    {
        return true;
    }

    /**
     * @return IcingaService
     */
    public function getServiceObjects()
    {
        if ($this->host_id) {
            $imports = $this->imports;
            if (empty($imports)) {
                return array();
            }

            $base = IcingaServiceSet::load(array(
                'object_name' => array_shift($imports),
                'object_type' => 'template'
            ), $this->getConnection());
        } else {
            $base = $this;
        }

        return $base->getMultiRelation('service')->getObjects();
    }

    public function renderToConfig(IcingaConfig $config)
    {
        if ($this->isTemplate()) {
            return;
        }

        if ($config->isLegacy()) {
            return $this->renderToLegacyConfig($config);
        }

        // Loop over all services belonging to this set
        // add our assign rules and then add the service to the config
        // eventually clone them beforehand to not get into trouble with caches
        // figure out whether we might need a zone property
        $file = $config->configFile(
            'zones.d/' . $this->getRenderingZone($config) . '/servicesets'
        );

        $file->prepend($this->getConfigHeaderComment($config));

        foreach ($this->getServiceObjects() as $service) {
            $service->object_type = $this->object_type;
            if ($this->isApplyRule()) {
                $service->setAssignments($this->getAssignments());
            }

            $service->vars = $this->vars;
            $service->host_id = $this->host_id;
            $file->addObject($service);
        }
    }

    protected function getConfigHeaderComment(IcingaConfig $config)
    {
        if ($config->isLegacy()) {
            $comment = "## Service Set '%s'\n\n";
        } else {
            $comment = "/** Service Set '%s' **/\n\n";
        }

        return sprintf($comment, $this->object_name);
    }

    public function renderToLegacyConfig(IcingaConfig $config)
    {
        if ($this->isTemplate()) {
            return;
        }

        if ($this->isApplyRule()) {
            // Not yet
            return;
        }

        // evaluate my assign rules once, get related hosts
        // Loop over all services belonging to this set
        // generate every service with host_name host1,host2...

        $file = $config->configFile(
            // TODO: zones.d?
            'zones.d/' . $this->getRenderingZone($config) . '/servicesets'
        );

        $file->prepend($this->getConfigHeaderComment($config));

        foreach ($this->getServiceObjects() as $service) {
            $service->object_type = 'object';
            $service->host_id = $this->host_id;
            $service->vars = $this->vars;
            $file->addLegacyObject($service);
        }
    }

    public function getRenderingZone(IcingaConfig $config = null)
    {
        return $this->connection->getDefaultGlobalZoneName();
    }
}
