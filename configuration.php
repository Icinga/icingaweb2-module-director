<?php

// Sample permission:
$this->providePermission('director/templates', 'Allow to modify templates');

$section = $this->menuSection($this->translate('Icinga Director'));

$section->setIcon('cubes');

// COMMAND
$section->add($this->translate('Commands'))
    ->setUrl('director/list/commands');
$section->add($this->translate('Command Arguments'))
    ->setUrl('director/list/commandarguments');

// KA
$section->add($this->translate('Timeperiods'))
    ->setUrl('director/list/timeperiods');

// HOST
$section->add($this->translate('Hosts'))
    ->setUrl('director/list/hosts');

// SERVICE
$section->add($this->translate('Services'))
    ->setUrl('director/list/services');
$section->add($this->translate('Servicegroups'))
    ->setUrl('director/list/servicegroups');

// USER
$section->add($this->translate('Users'))
    ->setUrl('director/list/users');
$section->add($this->translate('Usergroups'))
    ->setUrl('director/list/usergroups');

// HA
$section->add($this->translate('Zones'))
    ->setUrl('director/list/zones');
$section->add($this->translate('Endpoints'))
    ->setUrl('director/list/endpoints');

// INTERNAL
$section->add($this->translate('Config'))
    ->setUrl('director/list/generatedconfig')
    ->setPriority(902);

