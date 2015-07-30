<?php

$this->providePermission('director/hosts/read', $this->translate('Allow to configure hosts'));
$this->providePermission('director/hosts/write', $this->translate('Allow to configure hosts'));
$this->providePermission('director/templates/read', $this->translate('Allow to see template details'));
$this->providePermission('director/templates/write', $this->translate('Allow to configure templates'));

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
)->setIcon('cubes');

$section->add($this->translate('Global'))->setUrl('director/commands');
$section->add($this->translate('Hosts'))->setUrl('director/hosts');
$section->add($this->translate('Fields'))->setUrl('director/field/host')->setPriority(903);
$section->add($this->translate('Services'))->setUrl('director/services');
$section->add($this->translate('Users'))->setUrl('director/users');
$section->add($this->translate('Import / Sync'))
    ->setUrl('director/list/importsource')
    ->setPriority(901);
$section->add($this->translate('Config'))
    ->setUrl('director/list/generatedconfig')
    ->setPriority(902);

