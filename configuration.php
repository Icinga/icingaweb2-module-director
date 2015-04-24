<?php

$section = $this->menuSection($this->translate('Icinga Director'));

$section->setIcon('cubes');
$section->add($this->translate('Zones'))
    ->setUrl('director/list/zones');
$section->add($this->translate('Commands'))
    ->setUrl('director/list/commands');
$section->add($this->translate('Hosts'))
    ->setUrl('director/list/hosts');

