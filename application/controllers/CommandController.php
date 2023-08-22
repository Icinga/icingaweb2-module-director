<?php

namespace Icinga\Module\Director\Controllers;

use gipfl\Web\Widget\Hint;
use Icinga\Module\Director\Objects\IcingaCommandArgument;
use Icinga\Module\Director\Web\Table\BranchedIcingaCommandArgumentTable;
use ipl\Html\Html;
use Icinga\Module\Director\Forms\IcingaCommandArgumentForm;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Resolver\CommandUsage;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Web\Table\IcingaCommandArgumentTable;

class CommandController extends ObjectController
{
    /**
     * @throws \Icinga\Exception\AuthenticationException
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Security\SecurityException
     */
    public function init()
    {
        parent::init();
        $o = $this->object;
        if ($o && ! $o->isExternal()) {
            if ($this->getBranch()->isBranch()) {
                $urlParams = ['uuid' => $o->getUniqueId()->toString()];
            } else {
                $urlParams = ['name' => $o->getObjectName()];
            }
            #$this->tabs()->add('arguments', [
               # 'url'       => 'director/command/arguments',
              #  'urlParams' => $urlParams,
             #   'label'     => 'Arguments'
            #]);
        }
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Zend_Db_Select_Exception
     */
    public function indexAction()
    {
        if (! $this->getRequest()->isApiRequest()) {
            $this->showUsage();
        }
        parent::indexAction();
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Security\SecurityException
     * @throws \Zend_Db_Select_Exception
     */
    public function renderAction()
    {
        if ($this->object->isExternal()) {
            $this->showUsage();
        }

        parent::renderAction();
    }

    /**
     * @throws \Zend_Db_Select_Exception
     */
    protected function showUsage()
    {
        /** @var IcingaCommand $command */
        $command = $this->object;
        if ($command->isInUse()) {
            $usage = new CommandUsage($command);
            $this->content()->add(Hint::info(Html::sprintf(
                $this->translate('This Command is currently being used by %s'),
                Html::tag('span', null, $usage->getLinks())->setSeparator(', ')
            ))->addAttributes([
                'data-base-target' => '_next'
            ]));
        } else {
            $this->content()->add(Hint::warning($this->translate('This Command is currently not in use')));
        }
    }

    public function argumentsAction()
    {
        $p = $this->params;
        /** @var IcingaCommand $o */
        $o = $this->object;
        $this->tabs()->activate('arguments');
        $this->addTitle($this->translate('Command arguments: %s'), $o->getObjectName());
        $form = (new IcingaCommandArgumentForm)
            ->setBranch($this->getBranch())
            ->setCommandObject($o);
        if ($argument = $p->shift('argument')) {
            $this->addBackLink('director/command/arguments', [
                'name' => $p->get('name')
            ]);
            if ($this->branch->isBranch()) {
                $arguments = $o->arguments();
                $argument = $arguments->get($argument);
                // IcingaCommandArgument::create((array) $arguments->get($argument)->toFullPlainObject());
                // $argument->setBeingLoadedFromDb();
            } else {
                $argument = IcingaCommandArgument::load([
                    'command_id' => $o->get('id'),
                    'argument_name' => $argument
                ], $this->db());
            }
            $form->setObject($argument);
        }
        $form->handleRequest();
        $this->content()->add([$form]);
        if ($this->branch->isBranch()) {
            (new BranchedIcingaCommandArgumentTable($o, $this->getBranch()))->renderTo($this);
        } else {
            (new IcingaCommandArgumentTable($o, $this->getBranch()))->renderTo($this);
        }
    }

    protected function hasBasketSupport()
    {
        return true;
    }
}
