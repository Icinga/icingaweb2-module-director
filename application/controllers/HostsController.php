<?php

namespace Icinga\Module\Director\Controllers;

use gipfl\IcingaWeb2\Url;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Forms\IcingaAddServiceForm;
use Icinga\Module\Director\Forms\IcingaAddServiceSetForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Web\Controller\ObjectsController;
use gipfl\IcingaWeb2\Link;

class HostsController extends ObjectsController
{
    protected $multiEdit = array(
        'imports',
        'groups',
        'disabled'
    );

    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/hosts');
    }

    public function editAction()
    {
        $url = clone($this->getRequest()->getUrl());
        $url->setPath('director/hosts/addservice');

        $urlSet = clone($url);
        $urlSet->setPath('director/hosts/addserviceset');

        parent::editAction();

        $this->actions()->add(Link::create(
            $this->translate('Add Service'),
            $url,
            null,
            ['class' => 'icon-plus']
        ))->add(Link::create(
            $this->translate('Add Service Set'),
            $urlSet,
            null,
            ['class' => 'icon-plus']
        ));
    }

    public function edittemplatesAction()
    {
        parent::editAction();

        $objects = $this->loadMultiObjectsFromParams();
        $names = [];
        /** @var ExportInterface $object */
        foreach ($objects as $object) {
            $names[] = $object->getUniqueIdentifier();
        }

        $url = Url::fromPath('director/basket/add', [
            'type'  => 'HostTemplate',
        ]);

        $url->getParams()->addValues('names', $names);

        $this->actions()->add(Link::create(
            $this->translate('Add to Basket'),
            $url,
            null,
            ['class' => 'icon-tag']
        ));
    }

    public function addserviceAction()
    {
        $this->addSingleTab($this->translate('Add Service'));
        $filter = Filter::fromQueryString($this->params->toString());

        $objects = array();
        $db = $this->db();
        /** @var $filter FilterChain */
        foreach ($filter->filters() as $sub) {
            /** @var $sub FilterChain */
            foreach ($sub->filters() as $ex) {
                /** @var $ex FilterChain|FilterExpression */
                if ($ex->isExpression() && $ex->getColumn() === 'name') {
                    $name = $ex->getExpression();
                    $objects[$name] = IcingaHost::load($name, $db);
                }
            }
        }
        $this->addTitle(
            $this->translate('Add service to %d hosts'),
            count($objects)
        );

        $this->content()->add(
            IcingaAddServiceForm::load()
                ->setHosts($objects)
                ->setDb($this->db())
                ->handleRequest()
        );
    }

    public function addservicesetAction()
    {
        $this->addSingleTab($this->translate('Add Service Set'));
        $filter = Filter::fromQueryString($this->params->toString());

        $objects = array();
        $db = $this->db();
        /** @var $filter FilterChain */
        foreach ($filter->filters() as $sub) {
            /** @var $sub FilterChain */
            foreach ($sub->filters() as $ex) {
                /** @var $ex FilterChain|FilterExpression */
                if ($ex->isExpression() && $ex->getColumn() === 'name') {
                    $name = $ex->getExpression();
                    $objects[$name] = IcingaHost::load($name, $db);
                }
            }
        }
        $this->addTitle(
            $this->translate('Add Service Set to %d hosts'),
            count($objects)
        );

        $this->content()->add(
            IcingaAddServiceSetForm::load()
                ->setHosts($objects)
                ->setDb($this->db())
                ->handleRequest()
        );
    }
}
