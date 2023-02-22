<?php

namespace Icinga\Module\Director;

use Icinga\Authentication\Auth;
use Icinga\Authentication\Role;
use Icinga\Exception\AuthenticationException;

class Acl
{
    /** @var Auth */
    protected $auth;

    /** @var self */
    private static $instance;

    /**
     * @return self
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new static(Auth::getInstance());
        }

        return self::$instance;
    }

    /**
     * Acl constructor
     *
     * @param Auth $auth
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Whether the given permission is available
     *
     * @param $name
     *
     * @return bool
     */
    public function hasPermission($name)
    {
        return $this->auth->hasPermission($name);
    }

    /**
     * List all given roles
     *
     * @return array
     */
    public function listRoleNames()
    {
        return array_map(
            [$this, 'getNameForRole'],
            $this->getUser()->getRoles()
        );
    }

    /**
     * Get our user object, throws auth error if not available
     *
     * @return \Icinga\User
     * @throws AuthenticationException
     */
    protected function getUser()
    {
        if (null === ($user = $this->auth->getUser())) {
            throw new AuthenticationException('Authenticated user required');
        }

        return $user;
    }

    /**
     * Get the name for a given role
     *
     * @param Role $role
     *
     * @return string
     */
    protected function getNameForRole(Role $role)
    {
        return $role->getName();
    }
}
