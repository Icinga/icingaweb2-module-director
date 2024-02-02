<?php

namespace Icinga\Module\Director\Objects;

use Exception;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\Db\ServiceSetQueryBuilder;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Resolver\HostServiceBlacklist;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use stdClass;

class IcingaServiceSet extends IcingaObject implements ExportInterface
{
    protected $table = 'icinga_service_set';

    protected $defaultProperties = array(
        'id'                    => null,
        'uuid'                  => null,
        'host_id'               => null,
        'object_name'           => null,
        'object_type'           => null,
        'description'           => null,
        'assign_filter'         => null,
    );

    protected $uuidColumn = 'uuid';

    protected $keyName = array('host_id', 'object_name');

    protected $supportsImports = true;

    protected $supportsCustomVars = true;

    protected $supportsApplyRules = true;

    protected $supportedInLegacy = true;

    protected $relations = array(
        'host' => 'IcingaHost',
    );

    /** @var IcingaService[] Cached services */
    protected $cachedServices = [];

    /** @var IcingaService[]|null */
    private $services;

    /**
     * Set the services to be cached
     *
     * @param $services IcingaService[]
     * @return void
     */
    public function setCachedServices($services)
    {
        $this->cachedServices = $services;
    }

    /**
     * Get the cached services
     *
     * @return IcingaService[]
     */
    public function getCachedServices()
    {
        return $this->cachedServices;
    }

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
            $this->set('id', $key);
        } elseif (is_string($key)) {
            $keyComponents = preg_split('~!~', $key);
            if (count($keyComponents) === 1) {
                $this->set('object_name', $keyComponents[0]);
                $this->set('object_type', 'template');
            } else {
                throw new InvalidArgumentException(sprintf(
                    'Can not parse key: %s',
                    $key
                ));
            }
        } else {
            return parent::setKey($key);
        }

        return $this;
    }

    /**
     * @param stdClass[] $services
     * @return void
     */
    public function setServices(array $services)
    {
        $existing = $this->getServices();
        $uuidMap = [];
        foreach ($existing as $service) {
            $uuidMap[$service->getUniqueId()->getBytes()] = $service;
        }
        $this->services = [];
        foreach ($services as $service) {
            if (isset($service->uuid)) {
                $uuid = Uuid::fromString($service->uuid)->getBytes();
                $current = $uuidMap[$uuid] ?? IcingaService::create([], $this->connection);
            } else {
                if (! is_object($service)) {
                    var_dump($service);
                    exit;
                }
                $current = $existing[$service->object_name] ?? IcingaService::create([], $this->connection);
            }
            $current->setProperties((array) $service);
            $this->services[] = $current;
        }
    }

    protected function storeRelatedServices()
    {
        if ($this->services === null) {
            $cachedServices = $this->getCachedServices();
            if ($cachedServices) {
                $this->services = $cachedServices;
            } else {
                return;
            }
        }

        $seen = [];
        /** @var IcingaService $service */
        foreach ($this->services as $service) {
            $seen[$service->getUniqueId()->getBytes()] = true;
            $service->set('service_set_id', $this->get('id'));
            $service->store();
        }

        foreach ($this->fetchServices() as $service) {
            if (!isset($seen[$service->getUniqueId()->getBytes()])) {
                $service->delete();
            }
        }
    }

    /**
     * @deprecated
     * @return IcingaService[]
     * @throws \Icinga\Exception\NotFoundError
     */
    public function getServiceObjects()
    {
        // don't try to resolve services for unstored objects - as in getServiceObjectsForSet()
        // also for diff in activity log
        if ($this->get('id') === null) {
            return [];
        }

        if ($this->get('host_id')) {
            $imports = $this->imports()->getObjects();
            if (empty($imports)) {
                return array();
            }
            $parent = array_shift($imports);
            assert($parent instanceof IcingaServiceSet);
            return $this->getServiceObjectsForSet($parent);
        } else {
            return $this->getServiceObjectsForSet($this);
        }
    }

    /**
     * @param IcingaServiceSet $set
     * @return IcingaService[]
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getServiceObjectsForSet(IcingaServiceSet $set)
    {
        $connection = $this->getConnection();
        if (self::$dbObjectStore !== null) {
            $branchUuid = self::$dbObjectStore->getBranch()->getUuid();
        } else {
            $branchUuid = null;
        }

        $builder = new ServiceSetQueryBuilder($connection, $branchUuid);
        return $builder->fetchServicesWithQuery($builder->selectServicesForSet($set));
    }

    public function getUniqueIdentifier()
    {
        return $this->getObjectName();
    }

    public function beforeDelete()
    {
        $this->setCachedServices($this->getServices());
        // check if this is a template, or directly assigned to a host
        if ($this->get('host_id') === null) {
            // find all host sets and delete them
            foreach ($this->fetchHostSets() as $set) {
                $set->delete();
            }
        }

        parent::beforeDelete();
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function onDelete()
    {
        $hostId = $this->get('host_id');
        if ($hostId) {
            $deleteIds = [];
            foreach ($this->getServiceObjects() as $service) {
                if ($idToDelete = $service->get('id')) {
                    $deleteIds[] = (int) $idToDelete;
                }
            }

            if (! empty($deleteIds)) {
                $db = $this->getDb();
                $db->delete(
                    'icinga_host_service_blacklist',
                    $db->quoteInto(
                        sprintf('host_id = %s AND service_id IN (?)', $hostId),
                        $deleteIds
                    )
                );
            }
        }

        parent::onDelete();
    }

    /**
     * @param IcingaConfig $config
     * @throws \Icinga\Exception\NotFoundError
     */
    public function renderToConfig(IcingaConfig $config)
    {
        $files = [];
        $zone = $this->getRenderingZone($config) ;

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

        // Loop over all services belonging to this set
        // add our assign rules and then add the service to the config
        // eventually clone them beforehand to not get into trouble with caches
        // figure out whether we might need a zone property
        foreach ($services as $service) {
            if ($filter = $this->get('assign_filter')) {
                $service->set('object_type', 'apply');
                $service->set('assign_filter', $filter);
            } elseif ($hostId = $this->get('host_id')) {
                $host = $this->getRelatedObject('host', $hostId)->getObjectName();
                if (in_array($host, $this->getBlacklistedHostnames($service))) {
                    continue;
                }
                $service->set('object_type', 'object');
                $service->set('use_var_overrides', 'y');
                $service->set('host_id', $hostId);
            } else {
                // Service set template without assign filter or host
                continue;
            }

            $this->copyVarsToService($service);
            foreach ($service->getRenderingZones($config) as $serviceZone) {
                $file = $this->getConfigFileWithHeader($config, $serviceZone, $files);
                $file->addObject($service);
            }
        }

        if (empty($files)) {
            // always print the header, so you have minimal info present
            $this->getConfigFileWithHeader($config, $zone, $files);
        }
    }

    /**
     * @return array
     */
    public function getBlacklistedHostnames($service)
    {
        // Hint: if ($this->isApplyRule()) would be nice, but apply rules are
        // not enough, one might want to blacklist single services from Sets
        // assigned to single Hosts.
        if (PrefetchCache::shouldBeUsed()) {
            $lookup = PrefetchCache::instance()->hostServiceBlacklist();
        } else {
            $lookup = new HostServiceBlacklist($this->getConnection());
        }

        return $lookup->getBlacklistedHostnamesForService($service);
    }

    protected function getConfigFileWithHeader(IcingaConfig $config, $zone, &$files = [])
    {
        if (!isset($files[$zone])) {
            $file = $config->configFile(
                'zones.d/' . $zone . '/servicesets'
            );

            $file->addContent($this->getConfigHeaderComment($config));
            $files[$zone] = $file;
        }

        return $files[$zone];
    }

    protected function getConfigHeaderComment(IcingaConfig $config)
    {
        $name = $this->getObjectName();
        $assign = $this->get('assign_filter');

        if ($config->isLegacy()) {
            if ($assign !== null) {
                return "## applied Service Set '{$name}'\n\n";
            } else {
                return "## Service Set '{$name}' on this host\n\n";
            }
        } else {
            $comment = [
                "Service Set: {$name}",
            ];

            if (($host = $this->get('host')) !== null) {
                $comment[] = 'on host ' . $host;
            }

            if (($description = $this->get('description')) !== null) {
                $comment[] = '';
                foreach (preg_split('~\\n~', $description) as $line) {
                    $comment[] = $line;
                }
            }

            if ($assign !== null) {
                $comment[] = '';
                $comment[] = trim($this->renderAssign_Filter());
            }

            return "/**\n * " . join("\n * ", $comment) . "\n */\n\n";
        }
    }

    public function copyVarsToService(IcingaService $service)
    {
        $serviceVars = $service->vars();

        foreach ($this->vars() as $k => $var) {
            $serviceVars->$k = $var;
        }

        return $this;
    }

    /**
     * @param IcingaConfig $config
     * @throws \Icinga\Exception\NotFoundError
     */
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

            $hostnames = HostApplyMatches::forFilter($filter, $conn);
        } else {
            $hostnames = array($this->getRelated('host')->getObjectName());
        }

        $blacklists = [];

        foreach ($this->mapHostsToZones($hostnames) as $zone => $names) {
            $file = $config->configFile('director/' . $zone . '/servicesets', '.cfg');
            $file->addContent($this->getConfigHeaderComment($config));

            foreach ($this->getServiceObjects() as $service) {
                $object_name = $service->getObjectName();

                if (! array_key_exists($object_name, $blacklists)) {
                    $blacklists[$object_name] = $service->getBlacklistedHostnames();
                }

                // check if all hosts in the zone ignore this service
                $zoneNames = array_diff($names, $blacklists[$object_name]);

                $disabled = [];
                foreach ($zoneNames as $name) {
                    if (IcingaHost::load($name, $this->getConnection())->isDisabled()) {
                        $disabled[] = $name;
                    }
                }
                $zoneNames = array_diff($zoneNames, $disabled);

                if (empty($zoneNames)) {
                    continue;
                }

                $service->set('object_type', 'object');
                $service->set('host_id', $names);

                $this->copyVarsToService($service);

                $file->addLegacyObject($service);
            }
        }
    }

    public function getRenderingZone(IcingaConfig $config = null)
    {
        if ($this->get('host_id') === null) {
            if ($hostname = $this->get('host')) {
                $host = IcingaHost::load($hostname, $this->getConnection());
            } else {
                return $this->connection->getDefaultGlobalZoneName();
            }
        } else {
            $host = $this->getRelatedObject('host', $this->get('host_id'));
        }

        return $host->getRenderingZone($config);
    }

    public function createWhere()
    {
        $where = parent::createWhere();
        if (! $this->hasBeenLoadedFromDb()) {
            if (null === $this->get('host_id') && null === $this->get('id')) {
                $where .= " AND object_type = 'template'";
            }
        }

        return $where;
    }

    /**
     * @return IcingaService[]
     */
    public function getServices(): array
    {
        if ($this->services !== null) {
            return $this->services;
        }

        if ($this->hasBeenLoadedFromDb()) {
            return $this->fetchServices();
        }

        return [];
    }

    /**
     * @return IcingaService[]
     */
    public function fetchServices(): array
    {
        if ($store = self::$dbObjectStore) {
            $uuid = $store->getBranch()->getUuid();
        } else {
            $uuid = null;
        }
        $builder = new ServiceSetQueryBuilder($this->getConnection(), $uuid);
        return $builder->fetchServicesWithQuery($builder->selectServicesForSet($this));
    }

    /**
     * Fetch IcingaServiceSet that are based on this set and added to hosts directly
     *
     * @return IcingaServiceSet[]
     */
    public function fetchHostSets()
    {
        $id = $this->get('id');
        if ($id === null) {
            return [];
        }

        $query = $this->db->select()
            ->from(
                ['o' => $this->table]
            )->join(
                ['ssi' => $this->table . '_inheritance'],
                'ssi.service_set_id = o.id',
                []
            )->where(
                'ssi.parent_service_set_id = ?',
                $id
            );

        return static::loadAll($this->connection, $query);
    }

    /**
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function beforeStore()
    {
        parent::beforeStore();

        $name = $this->getObjectName();

        if ($this->isObject() && $this->get('host_id') === null && $this->get('host') === null) {
            throw new InvalidArgumentException(
                'A Service Set cannot be an object with no related host'
            );
        }
        // checking if template object_name is unique
        // TODO: Move to IcingaObject
        if (! $this->hasBeenLoadedFromDb() && $this->isTemplate() && static::exists($name, $this->connection)) {
            throw new DuplicateKeyException(
                '%s template "%s" already existing in database!',
                $this->getType(),
                $name
            );
        }
    }

    public function onStore()
    {
        $this->storeRelatedServices();
    }

    public function hasBeenModified()
    {
        if ($this->services !== null) {
            foreach ($this->services as $service) {
                if ($service->hasBeenModified()) {
                    return true;
                }
            }
        }

        return parent::hasBeenModified();
    }

    public function toSingleIcingaConfig()
    {
        $config = parent::toSingleIcingaConfig();

        try {
            foreach ($this->fetchHostSets() as $set) {
                $set->renderToConfig($config);
            }
        } catch (Exception $e) {
            $config->configFile(
                'failed-to-render'
            )->prepend(
                "/** Failed to render this Service Set **/\n"
                . '/*  ' . $e->getMessage() . ' */'
            );
        }

        return $config;
    }
}
