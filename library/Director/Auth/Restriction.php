<?php

namespace Icinga\Module\Director\Auth;

class Restriction
{
    public const MONITORING_RW_OBJECT_FILTER = 'director/monitoring/rw-object-filter';
    public const FILTER_HOSTGROUPS = 'director/filter/hostgroups';

    // Hint: by-name-Filters are being fetched with variable names, like "director/$type/apply/filter-by-name"
    public const NOTIFICATION_APPLY_FILTER_BY_NAME = 'director/notification/apply/filter-by-name';
    public const SCHEDULED_DOWNTIME_APPLY_FILTER_BY_NAME = 'director/scheduled-downtime/apply/filter-by-name';
    public const SERVICE_APPLY_FILTER_BY_NAME = 'director/service/apply/filter-by-name';
    public const SERVICE_SET_FILTER_BY_NAME = 'director/service_set/filter-by-name';
    public const HOST_TEMPLATE_FILTER_BY_NAME = 'director/host/template/filter-by-name';
    const DB_RESOURCE = 'director/db_resource';
}
