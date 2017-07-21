<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\IcingaCommandArgumentForm;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Web\Table\IcingaCommandArgumentTable;

class CommandController extends ObjectController
{
    public function init()
    {
        parent::init();
        $o = $this->object;
        if ($o && ! $o->isExternal()) {
            $this->tabs()->add('arguments', [
                'url'       => 'director/command/arguments',
                'urlParams' => ['name' => $o->getObjectName()],
                'label'     => 'Arguments'
            ]);
        }
    }

    public function argumentsAction()
    {
        $p = $this->params;
        /** @var IcingaCommand $o */
        $o = $this->object;
        $this->tabs()->activate('arguments');
        $this->addTitle($this->translate('Command arguments: %s'), $o->getObjectName());

        $form = IcingaCommandArgumentForm::load()->setCommandObject($o);
        if ($id = $p->shift('argument_id')) {
            $form->loadObject($id);
        }
        $form->handleRequest();
        $this->content()->add([$form]);
        IcingaCommandArgumentTable::create($o)->renderTo($this);
    }
}
