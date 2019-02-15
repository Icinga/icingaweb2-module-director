<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\RestApi\IcingaObjectsHandler;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Table\ApplyRulesTable;

class ServiceapplyrulesController extends ActionController
{
    protected $isApified = true;

    public function indexAction()
    {
        $request = $this->getRequest();
        if (! $request->isApiRequest()) {
            throw new NotFoundError('Not found');
        }

        $table = ApplyRulesTable::create('service', $this->db());
/*
       $query = $this->db()->getDbAdapter()
            ->select()
            ->from('icinga_service')
            ->where('object_type = ?', 'apply');
        $rules = IcingaService::loadAll($this->db(), $query);
*/

        $handler = (new IcingaObjectsHandler(
            $request,
            $this->getResponse(),
            $this->db()
        ))->setTable($table);

        $handler->dispatch();
    }
}
