<?php

namespace Icinga\Module\Director\Db\Cache;

use Icinga\Module\Director\CustomVariable\CustomVariable;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Resolver\HostServiceBlacklist;
use Icinga\Module\Director\Resolver\TemplateTree;
use LogicException;

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

    protected $hostServiceBlacklist;

    public static function initialize(Db $db)
    {
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
        } else {
            $checksum .= (int) $renderExpressions;
            if (! array_key_exists($checksum, $this->renderedVars)) {
                $this->renderedVars[$checksum] = $var->toConfigString($renderExpressions);
            }

            return $this->renderedVars[$checksum];
        }
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
