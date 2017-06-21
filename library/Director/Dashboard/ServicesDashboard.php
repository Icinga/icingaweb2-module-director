<?php

namespace Icinga\Module\Director\Dashboard;

class ServicesDashboard extends Dashboard
{
    protected $dashletNames = array(
        'SingleServices',
        'ServiceTemplates',
        'ServiceGroups',
        'ServiceApplyRules',
        'ServiceChoices',
        'ServiceSets'
    );

    public function getTitle()
    {
        return $this->translate('Manage your Icinga Service Checks');
    }

    public function getDescription()
    {
        return $this->translate(
            'This is where you manage your Icinga 2 Service Checks. Service'
            . ' Templates are your base building blocks, Service Sets allow'
            . ' you to assign multiple Services at once. Apply Rules make it'
            . ' possible to assign Services based on Host properties. And'
            . ' the list of all single Service Objects gives you the possibility'
            . ' to still modify (or delete) many of them at once.'
        );
    }
}
