<?php

use Icinga\Application\Icinga;
use Icinga\Module\Director\Auth\Permission;
use Icinga\Module\Director\Auth\Restriction;
use Icinga\Web\Window;

/** @var \Icinga\Application\Modules\Module $this */
if ($this->getConfig()->get('frontend', 'disabled', 'no') === 'yes') {
    return;
}

$this->providePermission(Permission::ALL_PERMISSIONS, $this->translate('Allow unrestricted access to Icinga Director'));
$this->providePermission(Permission::API, $this->translate('Allow to access the director API'));
$this->providePermission(Permission::AUDIT, $this->translate('Allow to access the full audit log'));
$this->providePermission(Permission::CLONE, $this->translate('Allow to clone objects'));
$this->providePermission(Permission::BASKETS, $this->translate('Allow to access the basket dashboard'));
$this->providePermission(Permission::DEPLOY, $this->translate('Allow to deploy configuration'));
$this->providePermission(Permission::INSPECT, $this->translate(
    'Allow to inspect objects through the Icinga 2 API (could contain sensitive information)'
));
$this->providePermission(Permission::SHOW_CONFIG, $this->translate(
    'Allow to show configuration (could contain sensitive information)'
));
$this->providePermission(Permission::SHOW_SQL, $this->translate(
    'Allow to show the full executed SQL queries in some places'
));
$this->providePermission(Permission::GROUPS_FOR_RESTRICTED_HOSTS, $this->translate(
    'Allow users with Hostgroup restrictions to access the Groups field'
));
$this->providePermission(Permission::HOSTS, $this->translate('Allow to configure hosts'));
$this->providePermission(Permission::HOST_CREATE, $this->translate('Allow to create hosts'));
$this->providePermission(Permission::NOTIFICATIONS, $this->translate(
    'Allow to configure notifications (unrestricted)'
));
$this->providePermission(Permission::OBJECTS_DELETE, $this->translate('Allow to delete objects'));
$this->providePermission(Permission::SERVICES, $this->translate('Allow to configure services'));
$this->providePermission(Permission::SERVICE_CREATE, $this->translate('Allow to configure services'));
$this->providePermission(Permission::SERVICE_SETS, $this->translate('Allow to configure service sets'));
$this->providePermission(Permission::SERVICE_SET_APPLY, $this->translate('Allow to define Service Set Apply Rules'));
$this->providePermission(Permission::USERS, $this->translate('Allow to configure users'));
$this->providePermission(Permission::SCHEDULED_DOWNTIMES, $this->translate(
    'Allow to configure notifications (unrestricted)'
));
$this->providePermission(Permission::MONITORING_HOSTS, $this->translate(
    'Allow users to modify Hosts they are allowed to see in the monitoring module'
));
$this->providePermission(Permission::MONITORING_SERVICES, $this->translate(
    'Allow users to modify Service they are allowed to see in the monitoring module'
));
$this->providePermission(Permission::MONITORING_SERVICES_RO, $this->translate(
    'Allow readonly users to see where a Service came from'
));

$this->provideRestriction(Restriction::FILTER_HOSTGROUPS, $this->translate(
    'Limit access to the given comma-separated list of hostgroups'
));
$this->provideRestriction(Restriction::MONITORING_RW_OBJECT_FILTER, $this->translate(
    'Additional (monitoring module) object filter to further restrict write access'
));
$this->provideRestriction(Restriction::NOTIFICATION_APPLY_FILTER_BY_NAME, $this->translate(
    'Filter available notification apply rules'
));
$this->provideRestriction(Restriction::SCHEDULED_DOWNTIME_APPLY_FILTER_BY_NAME, $this->translate(
    'Filter available scheduled downtime rules'
));
$this->provideRestriction(Restriction::SERVICE_APPLY_FILTER_BY_NAME, $this->translate(
    'Filter available service apply rules'
));
$this->provideRestriction(Restriction::SERVICE_SET_FILTER_BY_NAME, $this->translate(
    'Filter available service set templates. Use asterisks (*) as wildcards,'
    . ' like in DB* or *net*'
));

$this->provideSearchUrl($this->translate('Host configs'), 'director/hosts?limit=10', 60);

/*
// Disabled unless available
$this->provideRestriction(
    'director/hosttemplates/filter',
    $this->translate('Allow to use only host templates matching this filter')
);

$this->provideRestriction(
    'director/db_resource',
    $this->translate('Allow to use only these db resources (comma separated list)')
);
*/

$this->provideConfigTab('config', [
    'title' => 'Configuration',
    'url'   => 'settings'
]);
$mainTitle = N_('Icinga Director');

try {
    $app = Icinga::app();
    if ($app->isWeb()) {
        $request = $app->getRequest();
        $id = $request->getHeader('X-Icinga-WindowId');
        if ($id !== false) {
            $window = new Window($id);
            /** @var \Icinga\Web\Session\SessionNamespace $session */
            $session = $window->getSessionNamespace('director');
            $dbName = $session->get('db_resource');
            if ($dbName && $dbName !== $this->getConfig()->get('db', 'resource')) {
                $dbName = ucfirst(str_replace('_', ' ', $dbName));
                if (stripos($dbName, 'Director') === false) {
                    $dbName = 'Director: ' . $dbName;
                }
                $mainTitle = $dbName;
            }
        }
    }
} catch (\Exception $e) {
    // There isn't much we can do, we don't want to break the menu
    $mainTitle .= ' (?!)';
}

// Hint: director/admin and director/deployments are intentionally
$section = $this->menuSection($mainTitle)
    ->setUrl('director')
    ->setPriority(60)
    ->setIcon('cubes')
    ->setRenderer(['SummaryNavigationItemRenderer', 'state' => 'critical']);
$section->add(N_('Hosts'))
    ->setUrl('director/dashboard?name=hosts')
    ->setPermission(Permission::HOSTS)
    ->setPriority(30);
$section->add(N_('Services'))
    ->setUrl('director/dashboard?name=services')
    ->setPermission(Permission::SERVICES)
    ->setPriority(40);
$section->add(N_('Commands'))
    ->setUrl('director/dashboard?name=commands')
    ->setPermission(Permission::ADMIN)
    ->setPriority(50);
$section->add(N_('Notifications'))
    ->setUrl('director/dashboard?name=notifications')
    ->setPermission(Permission::NOTIFICATIONS)
    ->setPriority(70);
$section->add(N_('Automation'))
    ->setUrl('director/importsources')
    ->setPermission(Permission::ADMIN)
    ->setPriority(901);
$section->add(N_('Activity log'))
    ->setUrl('director/config/activities')
    ->setPriority(902)
    ->setPermission(Permission::AUDIT)
    ->setRenderer('ConfigHealthItemRenderer');
$section->add(N_('Deployments'))
    ->setUrl('director/config/deployments')
    ->setPriority(902)
    ->setPermission(Permission::DEPLOY);
