<?php

namespace Icinga\Module\Director\Web\Controller\Extension;

use Icinga\Module\Director\Db;

trait DirectorDb
{
    /** @var Db */
    private $db;

    /**
     * @throws \Icinga\Exception\ConfigurationError
     *
     * @return Db
     */
    public function db()
    {
        if ($this->db === null) {
            $resourceName = $this->Config()->get('db', 'resource');
            if ($resourceName) {
                $this->db = Db::fromResourceName($resourceName);
            } else {
                if ($this->getRequest()->isApiRequest()) {
                    throw new ConfigurationError('Icinga Director is not correctly configured');
                } else {
                    $this->redirectNow('director');
                }
            }
        }

        return $this->db;
    }
}
