<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use DirectoryIterator;
use Icinga\Exception\ProgrammingError;

class UserObjectDashlet extends Dashlet
{
    protected $icon = 'users';

    protected $requiredStats = array('user', 'usergroup');

    public function getTitle()
    {
        return $this->translate('Users / Contacts');
    }

    public function getUrl()
    {
        return 'director/users';
    }
}
