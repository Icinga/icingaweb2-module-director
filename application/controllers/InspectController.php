<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaEndpoint;
use Icinga\Module\Director\PlainObjectRenderer;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Table\CoreApiFieldsTable;
use Icinga\Module\Director\Web\Table\CoreApiObjectsTable;
use Icinga\Module\Director\Web\Table\CoreApiPrototypesTable;
use Icinga\Module\Director\Web\Tabs\ObjectTabs;
use Icinga\Module\Director\Web\Tree\InspectTreeRenderer;
use ipl\Html\Html;
use ipl\Html\Link;

class InspectController extends ActionController
{
    private $endpoint;

    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/inspect');
    }

    public function typesAction()
    {
        $object = $this->endpoint();
        $name = $object->getObjectName();
        $this->tabs(
            new ObjectTabs('endpoint', $this->Auth(), $object)
        )->activate('inspect');

        $this->addTitle($this->translate('Icinga 2 - Objects: %s'), $name);

        $this->actions()->add(
            Link::create(
                $this->translate('Status'),
                'director/inspect/status',
                ['endpoint' => $name],
                [
                    'class'            => 'icon-eye',
                    'data-base-target' => '_next'
                ]
            )
        );
        $this->content()->add(
            new InspectTreeRenderer($object)
        );
    }

    public function typeAction()
    {
        $api = $this->endpoint()->api();
        $typeName = $this->params->get('type');
        $this->addSingleTab($this->translate('Inspect - object list'));
        $this->addTitle(
            $this->translate('Object type "%s"'),
            $typeName
        );
        $c = $this->content();
        $type = $api->getType($typeName);
        if ($type->abstract) {
            $c->add($this->translate('This is an abstract object type.'));
        }

        if (! $type->abstract) {
            $objects = $api->listObjects($typeName, $type->plural_name);
            $c->add(Html::p(sprintf($this->translate('%d objects found'), count($objects))));
            $c->add(new CoreApiObjectsTable($objects, $this->endpoint(), $type));
        }

        if (count((array) $type->fields)) {
            $c->add([
                Html::h2($this->translate('Type attributes')),
                new CoreApiFieldsTable($type->fields, $this->url())
            ]);
        }

        if (count($type->prototype_keys)) {
            $c->add([
                Html::h2($this->translate('Prototypes (methods)')),
                new CoreApiPrototypesTable($type->prototype_keys, $type->name)
            ]);
        }
    }

    public function objectAction()
    {
        $name = $this->params->get('name');
        $pType = $this->params->get('plural');
        $this->addSingleTab($this->translate('Object Inspection'));
        $object = $this->endpoint()->api()->getObject($name, $pType);
        $this->addTitle('%s "%s"', $pType, $name);
        $this->content()->add(Html::pre(
            PlainObjectRenderer::render($object)
        ));
    }

    public function statusAction()
    {
        $this->addSingleTab($this->translate('Status'));
        $this->addTitle($this->translate('Icinga 2 API - Status'));
        $this->content()->add(
            Html::pre(PlainObjectRenderer::render($this->endpoint()->api()->getStatus()))
        );
    }

    protected function endpoint()
    {
        if ($this->endpoint === null) {
            if ($name = $this->params->get('endpoint')) {
                $this->endpoint = IcingaEndpoint::load($name, $this->db());
            } else {
                $this->endpoint = $this->db()->getDeploymentEndpoint();
            }
        }

        return $this->endpoint;
    }
}
