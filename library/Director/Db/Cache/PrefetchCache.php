<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Db\Cache;

use Icinga\Module\Director\CustomVariable\CustomVariable;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Resolver\HostServiceBlacklist;
use Icinga\Module\Director\Resolver\TemplateTree;
use LogicException;
use stdClass;

/**
 * Central prefetch cache
 *
 * Might be improved, accept various caches based on an interface and then
 * finally replace prefetch logic in DbObject itself. This would also allow
 * to get rid of IcingaObject-related code in this place
 */
class PrefetchCache
{
    protected $db;

    protected static $instance;

    protected $varsCaches = array();

    protected $groupsCaches = array();

    protected $templateResolvers = array();

    protected $renderedVars = array();

    protected $templateTrees = array();

    /** @var array<string, array<array-key, stdClass>> */
    protected array $linkedCustomProperties = [];

    protected $hostServiceBlacklist;

    public static function initialize(Db $db)
    {
        self::forget();
        self::$instance = new static($db);
    }

    protected function __construct(Db $db)
    {
        $this->db = $db;
    }

    /**
     * @throws LogicException
     *
     * @return self
     */
    public static function instance()
    {
        if (static::$instance === null) {
            throw new LogicException('Prefetch cache has not been loaded');
        }

        return static::$instance;
    }

    public static function forget()
    {
        DbObject::clearAllPrefetchCaches();
        self::$instance = null;
    }

    public static function shouldBeUsed()
    {
        return self::$instance !== null;
    }

    public function vars(IcingaObject $object)
    {
        return $this->varsCache($object)->getVarsForObject($object);
    }

    public function groups(IcingaObject $object)
    {
        return $this->groupsCache($object)->getGroupsForObject($object);
    }

    /**
     * Get linked property metadata for an ordered template list
     *
     * Successful results, including empty arrays, are reused for this cache's
     * lifetime. Loader failures propagate without being cached. Returned rows
     * are cloned so callers cannot change the cached metadata.
     *
     * Loader rows contain key_name, uuid, and value_type. UUIDs may be binary
     * strings or stream resources.
     *
     * @param string $type Short object table name
     * @param list<int|string> $templateNames Ordered ancestor template names
     * @param callable(): array<array-key, stdClass> $load Metadata loader
     *
     * @return array<array-key, stdClass> Metadata with UUIDs as binary strings
     *
     * @throws \Throwable If the metadata loader throws
     */
    public function linkedCustomProperties(string $type, array $templateNames, callable $load): array
    {
        $key = serialize([$type, $templateNames]);
        if (! array_key_exists($key, $this->linkedCustomProperties)) {
            $properties = [];
            foreach ($load() as $name => $property) {
                $property = clone $property;
                // PostgreSQL UUID streams cannot share their read position.
                $property->uuid = DbUtil::binaryResult($property->uuid);
                $properties[$name] = $property;
            }
            $this->linkedCustomProperties[$key] = $properties;
        }

        $properties = [];
        foreach ($this->linkedCustomProperties[$key] as $name => $property) {
            $properties[$name] = clone $property;
        }

        return $properties;
    }

    /* Hint: not implemented, this happens in DbObject right now
    public function byObjectType($type)
    {
        if (! array_key_exists($type, $this->caches)) {
            $this->caches[$type] = new ObjectCache($type);
        }

        return $this->caches[$type];
    }
    */

    public function renderVar(CustomVariable $var, $renderExpressions = false)
    {
        $checksum = $var->getChecksum();
        if (null === $checksum) {
            return $var->toConfigString($renderExpressions);
        }

        $cacheKey = $checksum . (int) $renderExpressions . $this->whiteListKey($var);
        if (! array_key_exists($cacheKey, $this->renderedVars)) {
            $this->renderedVars[$cacheKey] = $var->toConfigString($renderExpressions);
        }

        return $this->renderedVars[$cacheKey];
    }

    /**
     * Extra cache key part for the active whitelist, empty when there is none.
     *
     * Checksum only covers the value, not the whitelist used to render it. Apply-for
     * rules render the same checksum with a different whitelist per rule style, so
     * without this one rule's cached output could leak into the other.
     *
     * Skipping the cache for whitelisted vars would be simpler, but apply-for is
     * where caching pays off most. Many hosts inheriting one dictionary would render
     * it over and over instead of once. Hashing the whitelist in keeps that win.
     *
     * @param CustomVariable $var The var whose active whitelist gets hashed
     *
     * @return string
     */
    private function whiteListKey(CustomVariable $var): string
    {
        $whiteList = $var->getWhiteList();

        return $whiteList === [] ? '' : md5(serialize($whiteList));
    }

    public function hostServiceBlacklist()
    {
        if ($this->hostServiceBlacklist === null) {
            $this->hostServiceBlacklist = new HostServiceBlacklist($this->db);
            $this->hostServiceBlacklist->preloadMappings();
        }

        return $this->hostServiceBlacklist;
    }

    /**
     * @param IcingaObject $object
     * @return CustomVariableCache
     */
    protected function varsCache(IcingaObject $object)
    {
        $key = $object->getShortTableName();

        if (! array_key_exists($key, $this->varsCaches)) {
            $this->varsCaches[$key] = new CustomVariableCache($object);
        }

        return $this->varsCaches[$key];
    }

    protected function groupsCache(IcingaObject $object)
    {
        $key = $object->getShortTableName();

        if (! array_key_exists($key, $this->groupsCaches)) {
            $this->groupsCaches[$key] = new GroupMembershipCache($object);
        }

        return $this->groupsCaches[$key];
    }

    protected function templateTree(IcingaObject $object)
    {
        $key = $object->getShortTableName();
        if (! array_key_exists($key, $this->templateTrees)) {
            $this->templateTrees[$key] = new TemplateTree(
                $key,
                $object->getConnection()
            );
        }

        return $this->templateTrees[$key];
    }

    public function __destruct()
    {
        unset($this->groupsCaches);
        unset($this->varsCaches);
        unset($this->templateResolvers);
        unset($this->renderedVars);
    }
}
