<?php

namespace Icinga\Module\Director\Controllers;

use dipl\Html\Link;
use dipl\Web\Url;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Web\Controller\ObjectsController;

class ServicesController extends ObjectsController
{
    protected $multiEdit = array(
        'imports',
        'groups',
        'disabled'
    );

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
            'type'  => 'ServiceTemplate',
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
