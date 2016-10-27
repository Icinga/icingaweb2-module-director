<?php 

namespace Icinga\Module\Director;

use Icinga\Authentication\Auth;
use Icinga\Exception\AuthenticationException;

class Acl
{
    protected $auth;

    private static $instance;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new static(Auth::getInstance());
        }

        return self::$instance;
    }

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public function hasPermission($name)
    {
        return $this->auth->hasPermission($name);
    }

    protected function getUser()
    {
        if (null === ($user = $this->auth->getUser())) {
            throw new AuthenticationException('Authenticated user required');
        }

        return $user;
    }

    public function listRoleNames()
    {
        return array_map(
            array($this, 'getNameForRole'),
            $this->getUser()->getRoles()
        );
    }

    protected function getNameForRole($role)
    {
        return $role->getName();
    }
}
