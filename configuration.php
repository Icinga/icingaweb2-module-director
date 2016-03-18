<?php

$this->providePermission('director/api', $this->translate('Allow to access the director API'));
$this->providePermission('director/hosts/read', $this->translate('Allow to configure hosts'));
$this->providePermission('director/hosts/write', $this->translate('Allow to configure hosts'));
$this->providePermission('director/templates/read', $this->translate('Allow to see template details'));
$this->providePermission('director/templates/write', $this->translate('Allow to configure templates'));

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
$section->add($this->translate('Import / Sync'))
    ->setUrl('director/list/importsource')
    ->setPriority(901);
$section->add($this->translate('Deployments / History'))
    ->setUrl('director/config/deployments')
    ->setPriority(902)
    ->setRenderer('ConfigHealthItemRenderer');
