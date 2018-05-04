<?php

namespace Icinga\Module\Director\Controllers;

use dipl\Html\Html;
use Icinga\Module\Director\Forms\IcingaCommandArgumentForm;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Resolver\CommandUsage;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Web\Table\IcingaCommandArgumentTable;

class CommandController extends ObjectController
{
    /**
     * @throws \Icinga\Exception\ProgrammingError
     */
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

    /**
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function indexAction()
    {
        $this->showUsage();
        parent::indexAction();
    }

    /**
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function renderAction()
    {
        if ($this->object->isExternal()) {
            $this->showUsage();
        }

        parent::renderAction();
    }

    /**
     * @throws \Icinga\Exception\ProgrammingError
     */
    protected function showUsage()
    {
        /** @var IcingaCommand $command */
        $command = $this->object;
        if ($command->isInUse()) {
            $usage = new CommandUsage($command);
            $this->content()->add(Html::tag('p', [
                'class' => 'information',
                'data-base-target' => '_next'
            ], Html::sprintf(
                $this->translate('This Command is currently being used by %s'),
                Html::tag('span', null, $usage->getLinks())->setSeparator(', ')
            )));
        } else {
            $this->content()->add(Html::tag(
                'p',
                ['class' => 'warning'],
                $this->translate('This Command is currently not in use')
            ));
        }
    }

    /**
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function argumentsAction()
    {
        $p = $this->params;
        /** @var IcingaCommand $o */
        $o = $this->object;
        $this->tabs()->activate('arguments');
        $this->addTitle($this->translate('Command arguments: %s'), $o->getObjectName());
        $form = IcingaCommandArgumentForm::load()->setCommandObject($o);
        if ($id = $p->shift('argument_id')) {
            $this->addBackLink('director/command/arguments', [
                'name' => $p->get('name')
            ]);
            $form->loadObject($id);
        }
        $form->handleRequest();
        $this->content()->add([$form]);
        IcingaCommandArgumentTable::create($o)->renderTo($this);
    }
}
