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
use dipl\Html\Html;
use dipl\Html\Link;
use Icinga\Module\Director\Web\Widget\IcingaObjectInspection;

class InspectController extends ActionController
{
    private $endpoint;

    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/inspect');
    }

    /**
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
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

    /**
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
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
            $c->add(Html::tag('p', null, sprintf($this->translate('%d objects found'), count($objects))));
            $c->add(new CoreApiObjectsTable($objects, $this->endpoint(), $type));
        }

        if (count((array) $type->fields)) {
            $c->add([
                Html::tag('h2', null, $this->translate('Type attributes')),
                new CoreApiFieldsTable($type->fields, $this->url())
            ]);
        }

        if (count($type->prototype_keys)) {
            $c->add([
                Html::tag('h2', null, $this->translate('Prototypes (methods)')),
                new CoreApiPrototypesTable($type->prototype_keys, $type->name)
            ]);
        }
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function objectAction()
    {
        $name = $this->params->get('name');
        $pType = $this->params->get('plural');
        $this->addSingleTab($this->translate('Object Inspection'));
        $this->addTitle('%s "%s"', $pType, $name);
        $this->showEndpointInformation($this->endpoint());
        $this->content()->add(
            new IcingaObjectInspection(
                $this->endpoint()->api()->getObject($name, $pType),
                $this->db()
            )
        );
    }

    /**
     * @param IcingaEndpoint $endpoint
     * @throws \Icinga\Exception\IcingaException
     */
    protected function showEndpointInformation(IcingaEndpoint $endpoint)
    {
        $this->content()->add(
            Html::tag('p', null, Html::sprintf(
                'Inspected via %s (%s)',
                $this->linkToEndpoint($endpoint),
                $endpoint->getDescriptiveUrl()
            ))
        );
    }

    /**
     * @param IcingaEndpoint $endpoint
     * @return Link
     * @throws \Icinga\Exception\ProgrammingError
     * @throws \Icinga\Exception\IcingaException
     */
    protected function linkToEndpoint(IcingaEndpoint $endpoint)
    {
        return Link::create($endpoint->getObjectName(), 'director/endpoint', [
            'name' => $endpoint->getObjectName()
        ]);
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function statusAction()
    {
        $this->addSingleTab($this->translate('Status'));
        $this->addTitle($this->translate('Icinga 2 API - Status'));
        $this->content()->add(Html::tag(
            'pre',
            null,
            PlainObjectRenderer::render($this->endpoint()->api()->getStatus())
        ));
    }

    /**
     * @return IcingaEndpoint
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\NotFoundError
     */
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
