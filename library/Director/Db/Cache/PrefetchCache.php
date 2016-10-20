<?php

namespace Icinga\Module\Director\Db\Cache;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\CustomVariable\CustomVariable;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaTemplateResolver;

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

    public static function initialize(Db $db)
    {
        self::$instance = new static($db);
    }

    protected function __construct(Db $db)
    {
        $this->db = $db;
    }

    public static function instance()
    {
        if (static::$instance === null) {
            throw new ProgrammingError('Prefetch cache has not been loaded');
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

    public function imports(IcingaObject $object)
    {
        return $this->templateResolver($object)->setObject($object)->fetchParents();
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
            if (! array_key_exists($checksum, $this->renderedVars)) {
                $this->renderedVars[$checksum] = $var->toConfigString($renderExpressions);
            }

            return $this->renderedVars[$checksum];
        }
    }

    protected function varsCache(IcingaObject $object)
    {
        $key = $object->getShortTableName();

        if (! array_key_exists($key, $this->varsCaches)) {
            $this->varsCaches[$key] = new CustomVariableCache($object);
        }

        return $this->varsCaches[$key];
    }

    protected function templateResolver(IcingaObject $object)
    {
        $key = $object->getShortTableName();

        if (! array_key_exists($key, $this->templateResolvers)) {
            $this->templateResolvers[$key] = new IcingaTemplateResolver($object);
        }

        return $this->templateResolvers[$key];
    }

    protected function groupsCache(IcingaObject $object)
    {
        $key = $object->getShortTableName();

        if (! array_key_exists($key, $this->groupsCaches)) {
            $this->groupsCaches[$key] = new GroupMembershipCache($object);
        }

        return $this->groupsCaches[$key];
    }

    public function __destruct()
    {
        unset($this->groupsCaches);
        unset($this->varsCaches);
        unset($this->templateResolvers);
        unset($this->renderedVars);
    }
}
