<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectsController;
use gipfl\IcingaWeb2\Url;
use gipfl\IcingaWeb2\Link;

class CommandsController extends ObjectsController
{
    protected $multiEdit = array(
        'imports',
        'timeout',
        'is_string',
        'disabled'
    );

    public function indexAction()
    {
        parent::indexAction();
        $validTypes = ['object', 'external_object'];
        $type = $this->params->get('type', 'object');
        if (! in_array($type, $validTypes)) {
            $type = 'object';
        }

        $this->table->setType($type);
    }

    public function editAction()
    {
        parent::editAction();
        $objects = $this->loadMultiObjectsFromParams();
        $names = [];
        foreach ($objects as $object) {
            $names[] = $object->getUniqueIdentifier();
        }

        $url = url::fromPath('director/basket/add', [
            'type' => 'Command'
        ]);

        $url->getParams()->addValues('names', $names);

        $this->actions()->add(Link::create(
            $this->translate('Add to Basket'),
            $url,
            null,
            ['class' => 'icon-tag']
        ));
    }
}
