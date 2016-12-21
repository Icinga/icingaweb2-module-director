<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Data\Filter\Filter;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;


class IcingaServiceSet extends IcingaObject
{
    protected $table = 'icinga_service_set';

    protected $defaultProperties = array(
        'id'                    => null,
        'host_id'               => null,
        'object_name'           => null,
        'object_type'           => null,
        'description'           => null,
        'assign_filter'         => null,
    );

    protected $keyName = array('host_id', 'object_name');

    protected $supportsImports = true;

    protected $supportsCustomVars = true;

    protected $supportsApplyRules = true;

    protected $supportedInLegacy = true;

    protected $relations = array(
        'host' => 'IcingaHost',
    );

    public function isDisabled()
    {
        return false;
    }

    public function supportsAssignments()
    {
        return true;
    }

    protected function setKey($key)
    {
        if (is_int($key)) {
            $this->id = $key;
        } elseif (is_string($key)) {
            $keyComponents = preg_split('~!~', $key);
            if (count($keyComponents) === 1) {
                $this->set('object_name', $keyComponents[0]);
                $this->set('object_type', 'template');
            }
            else {
                throw new IcingaException('Can not parse key: %s', $key);
            }
        } else {
            return parent::setKey($key);
        }

        return $this;
    }

    /**
     * @return IcingaService[]
     */
    public function getServiceObjects()
    {
        if ($this->get('host_id')) {
            $imports = $this->imports()->getObjects();
            if (empty($imports)) {
                return array();
            }
            return $this->getServiceObjectsForSet(array_shift($imports));
        } else {
            return $this->getServiceObjectsForSet($this);
        }
    }

    protected function getServiceObjectsForSet(IcingaServiceSet $set)
    {
        if ($set->get('id') === null) {
            return array();
        }

        $connection = $this->getConnection();
        $db = $this->getDb();
        $ids = $db->fetchCol(
            $db->select()->from('icinga_service', 'id')
                ->where('service_set_id = ?', $set->get('id'))
        );

        $services = array();
        foreach ($ids as $id) {
            $service = IcingaService::load(array(
                'id' => $id,
                'object_type' => 'template'
            ), $connection);
            $service->set('service_set', null);

            $services[$service->getObjectName()] = $service;
        }

        return $services;
    }

    public function renderToConfig(IcingaConfig $config)
    {
        if ($this->get('assign_filter') === null && $this->isTemplate()) {
            return;
        }

        if ($config->isLegacy()) {
            $this->renderToLegacyConfig($config);
            return;
        }

        $services = $this->getServiceObjects();
        if (empty($services)) {
            return;
        }
        $file = $this->getConfigFileWithHeader($config);

        // Loop over all services belonging to this set
        // add our assign rules and then add the service to the config
        // eventually clone them beforehand to not get into trouble with caches
        // figure out whether we might need a zone property
        foreach ($services as $service) {
            if ($filter = $this->get('assign_filter')) {
                $service->set('object_type', 'apply');
                $service->set('assign_filter', $filter);
            } elseif ($hostId = $this->get('host_id')) {
                $service->set('object_type', 'object');
                $service->set('host_id', $this->get('host_id'));
            } else {
                // Service set template without assign filter or host
                continue;
            }

            $this->copyVarsToService($service);
            $file->addObject($service);
        }
    }

    protected function getConfigFileWithHeader(IcingaConfig $config)
    {
        $file = $config->configFile(
            'zones.d/' . $this->getRenderingZone($config) . '/servicesets'
        );

        $file->prepend($this->getConfigHeaderComment($config));
        return $file;
    }

    protected function getConfigHeaderComment(IcingaConfig $config)
    {
        if ($config->isLegacy()) {
            if ($this->get('assign_filter')) {
                $comment = "## applied Service Set '%s'\n\n";
            } else {
                $comment = "## Service Set '%s' on this host\n\n";
            }
        } else {
            $comment = "/** Service Set '%s' **/\n\n";
        }

        return sprintf($comment, $this->getObjectName());
    }

    protected function copyVarsToService(IcingaService $service)
    {
        $serviceVars = $service->vars();

        foreach ($this->vars() as $k => $var) {
            $serviceVars->$k = $var;
        }

        return $this;
    }

    public function renderToLegacyConfig(IcingaConfig $config)
    {
        if ($this->get('assign_filter') === null && $this->isTemplate()) {
            return;
        }

        // evaluate my assign rules once, get related hosts
        // Loop over all services belonging to this set
        // generate every service with host_name host1,host2... -> not yet. And Zones?

        $conn = $this->getConnection();

        // Delegating this to the service would look, but this way it's faster
        if ($filter = $this->get('assign_filter')) {
            $filter = Filter::fromQueryString($filter);
            $hosts = HostApplyMatches::forFilter($filter, $conn);
            foreach ($this->getServiceObjects() as $service) {
                $service->set('object_type', 'object');
                $this->copyVarsToService($service);

                foreach ($hosts as $hostname) {
                    $file = $this->legacyHostnameServicesFile($hostname, $config);
                    $file->addContent($this->getConfigHeaderComment($config));
                    $service->set('host', $hostname);
                    $file->addLegacyObject($service);
                }
            }
        } else {

            foreach ($this->getServiceObjects() as $service) {
                $service->set('object_type', 'object');
                $service->set('host_id', $this->get('host_id'));
                foreach ($this->vars() as $k => $var) {
                    $service->$k = $var;
                }
                $file = $this->legacyRelatedHostFile($service, $config);
                $file->addContent($this->getConfigHeaderComment($config));
                $file->addLegacyObject($service);
            }
        }
    }

    protected function legacyHostnameServicesFile($hostname, IcingaConfig $config)
    {
        $host = IcingaHost::load($hostname, $this->getConnection());
        return $config->configFile(
            'director/' . $host->getRenderingZone($config) . '/servicesets',
            '.cfg'
        );
    }

    protected function legacyRelatedHostFile(IcingaService $service, IcingaConfig $config)
    {
        return $config->configFile(
            'director/' . $service->getRelated('host')->getRenderingZone($config) . '/servicesets',
            '.cfg'
        );
    }

    public function getRenderingZone(IcingaConfig $config = null)
    {
        if ($this->get('host_id') === null) {
            return $this->connection->getDefaultGlobalZoneName();
        } else {
            $host = $this->getRelatedObject('host', $this->get('host_id'));
            return $host->getRenderingZone($config);
        }
    }

    protected function beforeStore()
    {
        parent::beforeStore();

        $name = $this->getObjectName();

        // checking if template object_name is unique
        // TODO: Move to IcingaObject
        if (! $this->hasBeenLoadedFromDb() && $this->isTemplate() && static::exists($name, $this->connection)) {
            throw new DuplicateKeyException('%s template "%s" already existing in database!', $this->getType(), $name);
        }
    }
}
