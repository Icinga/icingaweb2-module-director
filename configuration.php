<?php

$section = $this->menuSection($this->translate('Icinga Director'));

$section->setIcon('cubes');
$section->add($this->translate('Zones'))
    ->setUrl('director/list/zones');
$section->add($this->translate('Commands'))
    ->setUrl('director/list/commands');
$section->add($this->translate('Command Arguments'))
    ->setUrl('director/list/commandarguments');
$section->add($this->translate('Hosts'))
    ->setUrl('director/list/hosts');
$section->add($this->translate('Hostgroups'))
    ->setUrl('director/list/hostgroups');
$section->add($this->translate('Hostgroup Members'))
    ->setUrl('director/list/hostgroupmembers');
$section->add($this->translate('Host Vars'))
    ->setUrl('director/list/hostvars');
$section->add($this->translate('Servicegroups'))
    ->setUrl('director/list/servicegroups');
$section->add($this->translate('Users'))
    ->setUrl('director/list/users');
$section->add($this->translate('Usergroups'))
    ->setUrl('director/list/usergroups');
$section->add($this->translate('Endpoints'))
    ->setUrl('director/list/endpoints');
$section->add($this->translate('Activity Log'))
    ->setUrl('director/list/activitylog')
    ->setPriority(900);
