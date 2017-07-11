<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\NotFoundError;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Forms\IcingaMultiEditForm;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\ActionBar\ObjectsActionBar;
use Icinga\Module\Director\Web\ActionBar\TemplateActionBar;
use Icinga\Module\Director\Web\Table\ObjectSetTable;
use Icinga\Module\Director\Web\Table\ObjectsTable;
use Icinga\Module\Director\Web\Table\ServiceApplyRulesTable;
use Icinga\Module\Director\Web\Table\TemplatesTable;
use Icinga\Module\Director\Web\Tabs\ObjectsTabs;
use Icinga\Module\Director\Web\Tree\TemplateTreeRenderer;
use ipl\Html\Link;

abstract class ObjectsController extends ActionController
{
    protected $isApified = true;

    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/' . $this->getPluralBaseType());
    }
    /**
     * @return $this
     */
    protected function addObjectsTabs()
    {
        $tabName = $this->getRequest()->getActionName();
        if (substr($this->getType(), -5) === 'Group') {
            $tabName = 'groups';
        }
        $this->tabs(
            new ObjectsTabs($this->getBaseType(), $this->Auth())
        )->activate(
            $tabName
        );

        return $this;
    }

    public function indexAction()
    {
        $type = $this->getType();
        $this
            ->addObjectsTabs()
            ->setAutorefreshInterval(10)
            ->addTitle($this->translate(ucfirst(strtolower($type)) . 's'))
            ->actions(new ObjectsActionBar($type, $this->url()));

        ObjectsTable::create($type, $this->db())
            ->setAuth($this->Auth())
            ->renderTo($this);
    }

    public function editAction()
    {
        $type = ucfirst($this->getType());

        if (empty($this->multiEdit)) {
            throw new NotFoundError('Cannot edit multiple "%s" instances', $type);
        }

        $objects = $this->loadMultiObjectsFromParams();
        $formName = 'icinga' . $type;
        /** @var IcingaMultiEditForm $form */
        $form = $this->loadForm('IcingaMultiEdit')
            ->setObjects($objects)
            ->pickElementsFrom($this->loadForm($formName), $this->multiEdit);
        if ($type === 'Service') {
            $form->setListUrl('director/servicetemplate/hosts');
        }

        $form->handleRequest();

        $this
            ->addSingleTab($this->translate('Multiple objects'))
            ->addTitle(
                $this->translate('Modify %d objects'),
                count($objects)
            )->content()->add($form);
    }

    /**
     * Loads the TemplatesTable or the TemplateTreeRenderer
     *
     * Passing render=tree switches to the tree view.
     */
    public function templatesAction()
    {
        $type = $this->getType();
        $this
            ->assertPermission('director/admin')
            ->addObjectsTabs()
            ->addTitle(
                $this->translate('All your %s Templates'),
                $this->translate(ucfirst($type))
            )
            ->actions(new TemplateActionBar($type, $this->url()));

        $this->params->get('render') === 'tree'
            ? TemplateTreeRenderer::showType($type, $this, $this->db())
            : TemplatesTable::create($type, $this->db())->renderTo($this);
    }

    public function applyrulesAction()
    {
        $this
            ->assertPermission('director/admin')
            ->addObjectsTabs()
            ->addTitle($this->translate('All your Service Apply Rules'));
        $this->actions()/*->add(
            $this->getBackToDashboardLink()
        )*/->add(
            Link::create(
                $this->translate('Add'),
                'director/service/add',
                ['type' => 'apply_rule'],
                [
                    'title' => $this->translate('Create a new Service Apply Rule'),
                    'class' => 'icon-plus',
                    'data-base-target' => '_next'
                ]
            )
        );

        ServiceApplyRulesTable::show($this, $this->db());
    }

    public function setsAction()
    {
        $type = $this->getType();
        $this
            ->assertPermission('director/admin')
            ->addObjectsTabs()
            ->requireSupportFor('Sets')
            ->addTitle(
                $this->translate('Icinga %s Sets'),
                $this->translate(ucfirst($type))
            );

        ObjectSetTable::create($type, $this->db())->renderTo($this);
    }

    protected function loadMultiObjectsFromParams()
    {
        $filter = Filter::fromQueryString($this->params->toString());
        $type = $this->getType();
        $objects = array();
        $db = $this->db();
        /** @var $filter FilterChain */
        foreach ($filter->filters() as $sub) {
            /** @var $sub FilterChain */
            foreach ($sub->filters() as $ex) {
                /** @var $ex FilterChain|FilterExpression */
                $col = $ex->getColumn();
                if ($ex->isExpression()) {
                    if ($col === 'name') {
                        $name = $ex->getExpression();
                        $objects[$name] = IcingaObject::loadByType($type, $name, $db);
                    } elseif ($col === 'id') {
                        $name = $ex->getExpression();
                        $objects[$name] = IcingaObject::loadByType($type, ['id' => $name], $db);
                    }
                }
            }
        }

        return $objects;
    }

    /**
     * @param $feature
     * @return $this
     * @throws NotFoundError
     */
    protected function requireSupportFor($feature)
    {
        if ($this->supports($feature) !== true) {
            throw new NotFoundError(
                '%s does not support %s',
                $this->getType(),
                $feature
            );
        }

        return $this;
    }

    protected function supports($feature)
    {
        $func = "supports$feature";
        return IcingaObject::createByType($this->getType())->$func();
    }

    protected function getBaseType()
    {
        $type = $this->getType();
        if (substr($type, -5) === 'Group') {
            return substr($type, 0, -5);
        } else {
            return $type;
        }
    }

    protected function getType()
    {
        // Strip final 's' and upcase an eventual 'group'
        return preg_replace(
            array('/group$/', '/period$/', '/argument$/', '/apiuser$/'),
            array('Group', 'Period', 'Argument', 'ApiUser'),
            str_replace(
                'template',
                '',
                substr($this->getRequest()->getControllerName(), 0, -1)
            )
        );
    }

    protected function getPluralType()
    {
        return $this->getType() . 's';
    }

    protected function getPluralBaseType()
    {
        return $this->getBaseType() . 's';
    }
}
