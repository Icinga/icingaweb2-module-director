<?php

namespace Icinga\Module\Director\Web\Controller\Extension;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Web\Controller\ActionController;

trait DirectorDb
{
    /** @var Db */
    private $db;

    /**
     * @throws ConfigurationError
     *
     * @return Db
     */
    public function db()
    {
        if ($this->db === null) {
            $resourceName = $this->Config()->get('db', 'resource');
            if ($resourceName) {
                $this->db = Db::fromResourceName($resourceName);
            } elseif ($this instanceof ActionController) {
                if ($this->getRequest()->isApiRequest()) {
                    throw new ConfigurationError('Icinga Director is not correctly configured');
                } else {
                    $this->redirectNow('director');
                }
            } else {
                throw new ConfigurationError('Icinga Director is not correctly configured');
            }
        }

        return $this->db;
    }
}
