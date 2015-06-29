<?php

// Sample permission:
$this->providePermission('director/templates', 'Allow to modify templates');

$this->provideConfigTab('config', array(
    'title' => 'Configuration',
    'url'   => 'settings'
));

$section = $this->menuSection(
    $this->translate('Icinga Director')
)->setIcon('cubes');

$section->add($this->translate('Global'))->setUrl('director/list/commands');
$section->add($this->translate('Hosts'))->setUrl('director/list/hosts');
$section->add($this->translate('Services'))->setUrl('director/list/services');
$section->add($this->translate('Users'))->setUrl('director/list/users');
$section->add($this->translate('Config'))
    ->setUrl('director/list/generatedconfig')
    ->setPriority(902);

