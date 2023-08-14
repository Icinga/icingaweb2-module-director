<?php

namespace Icinga\Module\Director\Auth;

class Permission
{
    public const ALL_PERMISSIONS = 'director/*';
    public const ADMIN = 'director/admin'; // internal, assign ALL_PERMISSONS
    public const API = 'director/api';
    public const AUDIT = 'director/audit';
    public const CLONE = 'director/clone';
    public const DEPLOY = 'director/deploy';
    public const DEPLOYMENTS = 'director/deployments'; // internal, assign ALL_PERMISSONS
    public const GROUPS_FOR_RESTRICTED_HOSTS = 'director/groups-for-restricted-hosts';
    public const HOSTS = 'director/hosts';
    public const HOST_GROUPS = 'director/hostgroups'; // internal, assign ALL_PERMISSIONS
    public const INSPECT = 'director/inspect';
    public const MONITORING_SERVICES_RO = 'director/monitoring/services-ro';
    public const MONITORING_SERVICES = 'director/monitoring/services';
    public const MONITORING_HOSTS = 'director/monitoring/hosts';
    public const NOTIFICATIONS = 'director/notifications';
    public const SCHEDULED_DOWNTIMES = 'director/scheduled-downtimes';
    public const SERVICES = 'director/services';
    public const SERVICE_SETS = 'director/servicesets';
    public const SERVICE_SET_APPLY = 'director/service_set/apply';
    public const SHOW_CONFIG = 'director/showconfig';
    public const SHOW_SQL = 'director/showsql';
    public const USERS = 'director/users';
}
