<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class DatafieldCategoryDashlet extends Dashlet
{
    protected $icon = 'th-list';

    public function getTitle()
    {
        return $this->translate('Data Field Categories');
    }

    public function getSummary()
    {
        return $this->translate(
            'Categories bring structure to your Data Fields'
        );
    }

    public function getUrl()
    {
        return 'director/data/fieldcategories';
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }
}
