<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Data\Filter\Filter;

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
        $o = $this->object;
        $this->tabs()->activate('arguments');
        $this->setTitle($this->translate('Command arguments: %s'), $o->getObjectName());

        /** @var \Icinga\Module\Director\Forms\IcingaCommandArgumentForm $form */
        $form = $this->loadForm('icingaCommandArgument')->setCommandObject($o);
        if ($id = $p->shift('argument_id')) {
            $form->loadObject($id);
        }
        $form->handleRequest();

        $filter = Filter::where('command', $p->get('name'));
        $table = $this->loadTable('icingaCommandArgument')
            ->setCommandObject($o)
            ->setFilter($filter);

        $this->content()->add([$form, $table]);
    }
}
