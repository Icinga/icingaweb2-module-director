<?php

$this->providePermission('director/api', $this->translate('Allow to access the director API'));
$this->providePermission('director/ApiUsers/read', $this->translate('Allow to see api user details'));
$this->providePermission('director/ApiUsers/write', $this->translate('Allow to configure api users'));
$this->providePermission('director/commands/read', $this->translate('Allow to see command details'));
$this->providePermission('director/commands/write', $this->translate('Allow to configure commands'));
$this->providePermission('director/endpoints/read', $this->translate('Allow to see endpoint details'));
$this->providePermission('director/endpoints/write', $this->translate('Allow to configure endpoints'));
$this->providePermission('director/hosts/read', $this->translate('Allow to see host details'));
$this->providePermission('director/hosts/write', $this->translate('Allow to configure hosts'));
$this->providePermission('director/notifications/read', $this->translate('Allow to see notification details'));
$this->providePermission('director/notifications/write', $this->translate('Allow to configure notifications'));
$this->providePermission('director/services/read', $this->translate('Allow to see serive details'));
$this->providePermission('director/services/write', $this->translate('Allow to configure services'));
$this->providePermission('director/templates/read', $this->translate('Allow to see template details'));
$this->providePermission('director/templates/write', $this->translate('Allow to configure templates'));
$this->providePermission('director/timePeriods/read', $this->translate('Allow to see timeperiod details'));
$this->providePermission('director/timePeriods/write', $this->translate('Allow to configure timeperiods'));
$this->providePermission('director/users/read', $this->translate('Allow to see user details'));
$this->providePermission('director/users/write', $this->translate('Allow to configure users'));
$this->providePermission('director/zones/read', $this->translate('Allow to see zone details'));
$this->providePermission('director/zones/write', $this->translate('Allow to configure zones'));

$this->provideSearchUrl($this->translate('Host configs'), 'director/hosts?limit=10', 60);
$this->provideSearchUrl($this->translate('Service configs'), 'director/services?limit=10', 59);

$this->provideRestriction(
    'director/hosttemplates/filter',
    $this->translate('Allow to use only host templates matching this filter')
);

$this->provideRestriction(
    'director/dbresources/use',
    $this->translate('Allow to use only these db resources (comma separated list)')
);

$this->provideConfigTab('config', array(
    'title' => 'Configuration',
    'url'   => 'settings'
));

$section = $this->menuSection(
    $this->translate('Icinga Director')
)->setUrl('director')->setPriority(60)->setIcon(
    'cubes'
)->setRenderer(array(
    'SummaryNavigationItemRenderer',
    'state' => 'critical'
));

$section->add($this->translate('Hosts'))->setUrl('director/hosts')->setPriority(30);
$section->add($this->translate('Services'))->setUrl('director/services')->setPriority(40);
$section->add($this->translate('Commands'))->setUrl('director/commands')->setPriority(50);
$section->add($this->translate('Users'))->setUrl('director/users')->setPriority(70);
$section->add($this->translate('Automation'))
    ->setUrl('director/list/importsource')
    ->setPriority(901);
$section->add($this->translate('Config history'))
    ->setUrl('director/config/activities')
    ->setPriority(902)
    ->setRenderer('ConfigHealthItemRenderer');
