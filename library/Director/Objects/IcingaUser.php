<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Data\Db\DbConnection;
use Icinga\Module\Director\Data\PropertiesFilter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;

class IcingaUser extends IcingaObject implements ExportInterface
{
    protected $table = 'icinga_user';

    protected $defaultProperties = array(
        'id'                    => null,
        'uuid'                  => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
        'display_name'          => null,
        'email'                 => null,
        'pager'                 => null,
        'enable_notifications'  => null,
        'period_id'             => null,
        'zone_id'               => null,
    );

    protected $uuidColumn = 'uuid';

    protected $supportsGroups = true;

    protected $supportsCustomVars = true;

    protected $supportsFields = true;

    protected $supportsImports = true;

    protected $booleans = array(
        'enable_notifications' => 'enable_notifications'
    );

    protected $relatedSets = array(
        'states' => 'StateFilterSet',
        'types'  => 'TypeFilterSet',
    );

    protected $relations = array(
        'period' => 'IcingaTimePeriod',
        'zone'   => 'IcingaZone',
    );

    /** @var ?UserGroupMembershipResolver Resolver for user group memberships */
    protected $usergroupMembershipResolver;

    public function getUniqueIdentifier()
    {
        return $this->getObjectName();
    }

    protected function getUserGroupMembershipResolver()
    {
        if (! $this->usergroupMembershipResolver) {
            $this->usergroupMembershipResolver = new UserGroupMembershipResolver(
                $this->getConnection()
            );
        }

        return $this->usergroupMembershipResolver;
    }

    protected function notifyResolvers()
    {
        $resolver = $this->getUserGroupMembershipResolver();
        $resolver->addObject($this);
        $resolver->refreshDb();

        return $this;
    }

    /**
     * Enumerate properties for user objects
     *
     * @param ?DbConnection $connection
     * @param $prefix
     * @param $filter
     *
     * @return array
     */
    public static function enumProperties(DbConnection $connection = null, $prefix = '', $filter = null): array
    {
        $userProperties = array();
        if ($filter === null) {
            $filter = new PropertiesFilter();
        }

        $realProperties = array_merge(['templates'], static::create()->listProperties());
        sort($realProperties);

        if ($filter->match(PropertiesFilter::$USER_PROPERTY, 'name')) {
            $userProperties[$prefix . 'name'] = 'name';
        }

        foreach ($realProperties as $prop) {
            if (!$filter->match(PropertiesFilter::$USER_PROPERTY, $prop)) {
                continue;
            }

            if (substr($prop, -3) === '_id') {
                if ($prop === 'template_choice_id') {
                    continue;
                }
                $prop = substr($prop, 0, -3);
            }

            $userProperties[$prefix . $prop] = $prop;
        }

        unset($userProperties[$prefix . 'uuid']);
        unset($userProperties[$prefix . 'custom_endpoint_name']);

        $userVars = [];

        if ($connection instanceof Db) {
            foreach ($connection->fetchDistinctUserVars() as $var) {
                if ($filter->match(PropertiesFilter::$CUSTOM_PROPERTY, $var->varname, $var)) {
                    if ($var->datatype) {
                        $userVars[$prefix . 'vars.' . $var->varname] = sprintf(
                            '%s (%s)',
                            $var->varname,
                            $var->caption
                        );
                    } else {
                        $userVars[$prefix . 'vars.' . $var->varname] = $var->varname;
                    }
                }
            }
        }

        //$properties['vars.*'] = 'Other custom variable';
        ksort($userVars);


        $props = mt('director', 'User properties');
        $vars  = mt('director', 'Custom variables');

        $properties = [];
        if (! empty($userProperties)) {
            $properties[$props] = $userProperties;
            $properties[$props][$prefix . 'groups'] = 'Groups';
        }

        if (! empty($userVars)) {
            $properties[$vars] = $userVars;
        }

        return $properties;
    }
}
