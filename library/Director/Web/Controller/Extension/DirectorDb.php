<?php

namespace Icinga\Module\Director\Web\Controller\Extension;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Web\Controller\ActionController;
use RuntimeException;

trait DirectorDb
{
    /** @var Db */
    private $db;

    protected function getDbResourceName()
    {
        if ($name = $this->getPreferredDbResourceName()) {
            return $name;
        } else {
            return $this->getFirstDbResourceName();
        }
    }

    protected function getPreferredDbResourceName()
    {
        return $this->getWindowSessionValue('db_resource');
    }

    protected function getFirstDbResourceName()
    {
        $names = $this->listAllowedDbResourceNames();
        if (empty($names)) {
            return null;
        } else {
            return array_shift($names);
        }
    }

    protected function listAllowedDbResourceNames()
    {
        /** @var \Icinga\Authentication\Auth $auth */
        $auth = $this->Auth();

        $available = $this->listAvailableDbResourceNames();
        if ($resourceNames = $auth->getRestrictions('director/db_resource')) {
            $names = [];
            foreach ($resourceNames as $names) {
                foreach ($this->splitList($names) as $name) {
                    if (array_key_exists($name, $available)) {
                        $names[] = $name;
                    }
                }
            }

            return $names;
        } else {
            return $available;
        }
    }

    /**
     * @param string $string
     * @return array
     */
    protected function splitList($string)
    {
        return preg_split('/\s*,\s*/', $string, -1, PREG_SPLIT_NO_EMPTY);
    }

    protected function isMultiDbSetup()
    {
        return count($this->listAvailableDbResourceNames()) > 1;
    }

    /**
     * @return array
     */
    protected function listAvailableDbResourceNames()
    {
        /** @var \Icinga\Application\Config $config */
        $config = $this->Config();
        $resources = $config->get('db', 'resources');
        if ($resources === null) {
            $resource = $config->get('db', 'resource');
            if ($resource === null) {
                return [];
            } else {
                return [$resource => $resource];
            }
        } else {
            $resources = $this->splitList($resources);
            $resources = array_combine($resources, $resources);
            // natsort doesn't work!?
            ksort($resources, SORT_NATURAL);
            if ($resource = $config->get('db', 'resource')) {
                unset($resources[$resource]);
                $resources = [$resource => $resource] + $resources;
            }

            return $resources;
        }
    }

    protected function getWindowSessionValue($value, $default = null)
    {
        /** @var \Icinga\Web\Window $window */
        $window = $this->Window();
        /** @var \Icinga\Web\Session\SessionNamespace $session */
        $session = $window->getSessionNamespace('director');

        return $session->get($value, $default);
    }

    /**
     *
     * @return Db
     */
    public function db()
    {
        if ($this->db === null) {
            $resourceName = $this->getDbResourceName();
            if ($resourceName) {
                $this->db = Db::fromResourceName($resourceName);
            } elseif ($this instanceof ActionController) {
                if ($this->getRequest()->isApiRequest()) {
                    throw new RuntimeException('Icinga Director is not correctly configured');
                } else {
                    $this->redirectNow('director');
                }
            } else {
                throw new RuntimeException('Icinga Director is not correctly configured');
            }
        }

        return $this->db;
    }
}
