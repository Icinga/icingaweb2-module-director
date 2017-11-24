<?php

$this->providePermission('director/api', $this->translate('Allow to access the director API'));
$this->providePermission('director/audit', $this->translate('Allow to access the full audit log'));
$this->providePermission(
    'director/showconfig',
    $this->translate('Allow to show configuration (could contain sensitive information)')
);
$this->providePermission(
    'director/showsql',
    $this->translate('Allow to show the full executed SQL queries in some places')
);
$this->providePermission('director/deploy', $this->translate('Allow to deploy configuration'));
$this->providePermission('director/hosts', $this->translate('Allow to configure hosts'));
$this->providePermission('director/services', $this->translate('Allow to configure services'));
$this->providePermission('director/servicesets', $this->translate('Allow to configure service sets'));
$this->providePermission('director/service_set/apply', $this->translate('Allow to define Service Set Apply Rules'));
$this->providePermission('director/users', $this->translate('Allow to configure users'));
$this->providePermission('director/notifications', $this->translate('Allow to configure notifications'));
$this->providePermission(
    'director/inspect',
    $this->translate(
        'Allow to inspect objects through the Icinga 2 API (could contain sensitive information)'
    )
);
$this->providePermission('director/*', $this->translate('Allow unrestricted access to Icinga Director'));

$this->provideRestriction(
    'director/filter/hostgroups',
    $this->translate(
        'Limit access to the given comma-separated list of hostgroups'
    )
);

$this->provideRestriction(
    'director/service/apply/filter-by-name',
    $this->translate(
        'Filter available service apply rules'
    )
);

$this->provideRestriction(
    'director/notification/apply/filter-by-name',
    $this->translate(
        'Filter available notification apply rules'
    )
);

$this->provideRestriction(
    'director/service_set/filter-by-name',
    $this->translate(
        'Filter available service set templates. Use asterisks (*) as wildcards,'
        . ' like in DB* or *net*'
    )
);

$this->provideSearchUrl($this->translate('Host configs'), 'director/hosts?limit=10', 60);

/*
// Disabled unless available
$this->provideRestriction(
    'director/hosttemplates/filter',
    $this->translate('Allow to use only host templates matching this filter')
);

$this->provideRestriction(
    'director/dbresources/use',
    $this->translate('Allow to use only these db resources (comma separated list)')
);
*/

$this->provideConfigTab('config', array(
    'title' => 'Configuration',
    'url'   => 'settings'
));

$section = $this->menuSection(
    N_('Icinga Director')
)->setUrl('director')->setPriority(60)->setIcon(
    'cubes'
)->setRenderer(array(
    'SummaryNavigationItemRenderer',
    'state' => 'critical'
));

$section->add(N_('Hosts'))
    ->setUrl('director/dashboard?name=hosts')
    ->setPermission('director/hosts')
    ->setPriority(30);
$section->add(N_('Services'))
    ->setUrl('director/dashboard?name=services')
    ->setPermission('director/services')
    ->setPriority(40);
$section->add(N_('Commands'))
    ->setUrl('director/dashboard?name=commands')
    ->setPermission('director/admin')
    ->setPriority(50);
$section->add(N_('Notifications'))
    ->setUrl('director/dashboard?name=notifications')
    ->setPermission('director/notifications')
    ->setPriority(70);
$section->add(N_('Automation'))
    ->setUrl('director/importsources')
    ->setPermission('director/admin')
    ->setPriority(901);
$section->add(N_('Activity log'))
    ->setUrl('director/config/activities')
    ->setPriority(902)
    ->setPermission('director/audit')
    ->setRenderer('ConfigHealthItemRenderer');
$section->add(N_('Deployments'))
    ->setUrl('director/config/deployments')
    ->setPriority(902)
    ->setPermission('director/deployments');
