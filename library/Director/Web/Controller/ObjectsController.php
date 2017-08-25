<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\NotFoundError;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Forms\IcingaMultiEditForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\RestApi\IcingaObjectsHandler;
use Icinga\Module\Director\Web\ActionBar\ObjectsActionBar;
use Icinga\Module\Director\Web\ActionBar\TemplateActionBar;
use Icinga\Module\Director\Web\Table\ApplyRulesTable;
use Icinga\Module\Director\Web\Table\ObjectSetTable;
use Icinga\Module\Director\Web\Table\ObjectsTable;
use Icinga\Module\Director\Web\Table\TemplatesTable;
use Icinga\Module\Director\Web\Tabs\ObjectsTabs;
use Icinga\Module\Director\Web\Tree\TemplateTreeRenderer;
use ipl\Html\Link;

abstract class ObjectsController extends ActionController
{
    protected $isApified = true;

    /** @var ObjectsTable */
    protected $table;

    protected function checkDirectorPermissions()
    {
        if ($this->getRequest()->getActionName() !== 'sets') {
            $this->assertPermission('director/' . $this->getPluralBaseType());
        }
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
        $this->tabs(new ObjectsTabs($this->getBaseType(), $this->Auth()))
            ->activate($tabName);

        return $this;
    }

    protected function apiRequestHandler()
    {
        $request = $this->getRequest();
        $table = $this->getTable();
        if ($request->getControllerName() === 'services'
            && $host = $this->params->get('host')
        ) {
            $host = IcingaHost::load($host, $this->db());
            $table->getQuery()->where('host_id = ?', $host->get('id'));
        }

        if ($request->getActionName() === 'templates') {
            $table->filterObjectType('template');
        }

        return (new IcingaObjectsHandler(
            $request,
            $this->getResponse(),
            $this->db()
        ))->setTable($table);
    }

    public function indexAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            $this->apiRequestHandler()->dispatch();
            return;
        }
        $type = $this->getType();
        $this
            ->addObjectsTabs()
            ->setAutorefreshInterval(10)
            ->addTitle($this->translate(ucfirst($this->getPluralType())))
            ->actions(new ObjectsActionBar($type, $this->url()));

        if ($type === 'command' && $this->params->get('type') === 'external_object') {
            $this->tabs()->activate('external');
        }

        // Hint: might be used in controllers extending this
        $this->table = $this->getTable();
        $this->table->renderTo($this);
    }

    protected function getTable()
    {
        return ObjectsTable::create($this->getType(), $this->db())
            ->setAuth($this->getAuth());
    }

    public function editAction()
    {
        $type = ucfirst($this->getType());

        if (empty($this->multiEdit)) {
            throw new NotFoundError('Cannot edit multiple "%s" instances', $type);
        }

        $objects = $this->loadMultiObjectsFromParams();
        $formName = 'icinga' . $type;
        $form = IcingaMultiEditForm::load()
            ->setObjects($objects)
            ->pickElementsFrom($this->loadForm($formName), $this->multiEdit);
        if ($type === 'Service') {
            $form->setListUrl('director/services');
        } elseif ($type === 'Host') {
            $form->setListUrl('director/hosts');
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
        if ($this->getRequest()->isApiRequest()) {
            $this->apiRequestHandler()->dispatch();
            return;
        }
        $type = $this->getType();
        $shortType = IcingaObject::createByType($type)->getShortTableName();
        $this
            ->assertPermission('director/admin')
            ->addObjectsTabs()
            ->setAutorefreshInterval(10)
            ->addTitle(
                $this->translate('All your %s Templates'),
                $this->translate(ucfirst($type))
            )
            ->actions(new TemplateActionBar($shortType, $this->url()));

        $this->params->get('render') === 'tree'
            ? TemplateTreeRenderer::showType($shortType, $this, $this->db())
            : TemplatesTable::create($shortType, $this->db())->renderTo($this);
    }

    protected function assertApplyRulePermission()
    {
        return $this->assertPermission('director/admin');
    }

    public function applyrulesAction()
    {
        $type = $this->getType();
        $tType = $this->translate(ucfirst($type));
        $this
            ->assertApplyRulePermission()
            ->addObjectsTabs()
            ->setAutorefreshInterval(10)
            ->addTitle(
                $this->translate('All your %s Apply Rules'),
                $tType
            );
        $this->actions()/*->add(
            $this->getBackToDashboardLink()
        )*/->add(
            Link::create(
                $this->translate('Add'),
                "director/$type/add",
                ['type' => 'apply'],
                [
                    'title' => sprintf(
                        $this->translate('Create a new %s Apply Rule'),
                        $tType
                    ),
                    'class' => 'icon-plus',
                    'data-base-target' => '_next'
                ]
            )
        );

        $table = new ApplyRulesTable($this->db());
        $table->setType($this->getType());
        $table->renderTo($this);
    }

    public function setsAction()
    {
        $type = $this->getType();
        $tType = $this->translate(ucfirst($type));
        $this
            ->assertPermission('director/' . $this->getBaseType() . '_sets')
            ->addObjectsTabs()
            ->requireSupportFor('Sets')
            ->setAutorefreshInterval(10)
            ->addTitle(
                $this->translate('Icinga %s Sets'),
                $tType
            );

        $this->actions()->add(
            Link::create(
                $this->translate('Add'),
                "director/${type}set/add",
                null,
                [
                    'title' => sprintf(
                        $this->translate('Create a new %s Set'),
                        $tType
                    ),
                    'class' => 'icon-plus',
                    'data-base-target' => '_next'
                ]
            )
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
            array('/group$/', '/period$/', '/argument$/', '/apiuser$/', '/dependencie$/'),
            array('Group', 'Period', 'Argument', 'ApiUser', 'dependency'),
            str_replace(
                'template',
                '',
                substr($this->getRequest()->getControllerName(), 0, -1)
            )
        );
    }

    protected function getPluralType()
    {
        return preg_replace('/cys$/', 'cies', $this->getType() . 's');
    }

    protected function getPluralBaseType()
    {
        return preg_replace('/cys$/', 'cies', $this->getBaseType() . 's');
    }
}
