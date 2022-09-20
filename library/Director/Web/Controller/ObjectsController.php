<?php

namespace Icinga\Module\Director\Web\Controller;

use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\NotFoundError;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\Db\DbObjectStore;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Forms\IcingaMultiEditForm;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\RestApi\IcingaObjectsHandler;
use Icinga\Module\Director\Web\ActionBar\ObjectsActionBar;
use Icinga\Module\Director\Web\ActionBar\TemplateActionBar;
use Icinga\Module\Director\Web\Form\FormLoader;
use Icinga\Module\Director\Web\Table\ApplyRulesTable;
use Icinga\Module\Director\Web\Table\ObjectSetTable;
use Icinga\Module\Director\Web\Table\ObjectsTable;
use Icinga\Module\Director\Web\Table\TemplatesTable;
use Icinga\Module\Director\Web\Tabs\ObjectsTabs;
use Icinga\Module\Director\Web\Tree\TemplateTreeRenderer;
use gipfl\IcingaWeb2\Link;
use Icinga\Module\Director\Web\Widget\AdditionalTableActions;
use Icinga\Module\Director\Web\Widget\BranchedObjectsHint;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

abstract class ObjectsController extends ActionController
{
    use BranchHelper;

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
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     */
    protected function addObjectsTabs()
    {
        $tabName = $this->getRequest()->getActionName();
        if (substr($this->getType(), -5) === 'Group') {
            $tabName = 'groups';
        }
        $this->tabs(new ObjectsTabs(
            $this->getBaseType(),
            $this->Auth(),
            $this->getBaseObjectUrl()
        ))->activate($tabName);

        return $this;
    }

    /**
     * @return IcingaObjectsHandler
     * @throws NotFoundError
     */
    protected function apiRequestHandler()
    {
        $request = $this->getRequest();
        $table = $this->getTable();
        if ($request->getControllerName() === 'services'
            && $host = $this->params->get('host')
        ) {
            $host = IcingaHost::load($host, $this->db());
            $table->getQuery()->where('o.host_id = ?', $host->get('id'));
        }

        if ($request->getActionName() === 'templates') {
            $table->filterObjectType('template');
        } elseif ($request->getActionName() === 'applyrules') {
            $table->filterObjectType('apply');
        }
        $search = $this->params->get('q');
        if ($search !== null && \strlen($search) > 0) {
            $table->search($search);
        }

        return (new IcingaObjectsHandler(
            $request,
            $this->getResponse(),
            $this->db()
        ))->setTable($table);
    }

    /**
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws NotFoundError
     */
    public function indexAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            $this->apiRequestHandler()->dispatch();
            return;
        }

        $type = $this->getType();
        if ($this->params->get('format') === 'json') {
            $filename = sprintf(
                "director-${type}_%s.json",
                date('YmdHis')
            );
            $this->getResponse()->setHeader('Content-disposition', "attachment; filename=$filename", true);
            $this->apiRequestHandler()->dispatch();
            return;
        }

        $this
            ->addObjectsTabs()
            ->setAutorefreshInterval(10)
            ->addTitle($this->translate(ucfirst($this->getPluralType())))
            ->actions(new ObjectsActionBar($this->getBaseObjectUrl(), $this->url()));

        $this->content()->add(new BranchedObjectsHint($this->getBranch(), $this->Auth()));

        if ($type === 'command' && $this->params->get('type') === 'external_object') {
            $this->tabs()->activate('external');
        }

        // Hint: might be used in controllers extending this
        $this->table = $this->eventuallyFilterCommand($this->getTable());

        $this->table->renderTo($this);
        (new AdditionalTableActions($this->getAuth(), $this->url(), $this->table))
            ->appendTo($this->actions());
    }

    /**
     * @return ObjectsTable
     */
    protected function getTable()
    {
        $table = ObjectsTable::create($this->getType(), $this->db())
            ->setAuth($this->getAuth())
            ->setBranchUuid($this->getBranchUuid())
            ->setBaseObjectUrl($this->getBaseObjectUrl());

        return $table;
    }

    /**
     * @return ApplyRulesTable
     * @throws NotFoundError
     */
    protected function getApplyRulesTable()
    {
        $table = new ApplyRulesTable($this->db());
        $table->setType($this->getType())
            ->setBaseObjectUrl($this->getBaseObjectUrl());
        $this->eventuallyFilterCommand($table);

        return $table;
    }

    /**
     * @throws NotFoundError
     */
    public function edittemplatesAction()
    {
        $this->commonForEdit();
    }

    /**
     * @throws NotFoundError
     */
    public function editAction()
    {
        $this->commonForEdit();
    }

    /**
     * @throws NotFoundError
     */
    public function commonForEdit()
    {
        $type = ucfirst($this->getType());

        if (empty($this->multiEdit)) {
            throw new NotFoundError('Cannot edit multiple "%s" instances', $type);
        }

        $objects = $this->loadMultiObjectsFromParams();
        if (empty($objects)) {
            throw new NotFoundError('No "%s" instances have been loaded', $type);
        }
        $formName = 'icinga' . $type;
        $form = IcingaMultiEditForm::load()
            ->setBranch($this->getBranch())
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
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws \Icinga\Security\SecurityException
     * @throws NotFoundError
     */
    public function templatesAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            $this->apiRequestHandler()->dispatch();
            return;
        }
        $type = $this->getType();

        if ($this->params->get('format') === 'json') {
            $filename = sprintf(
                "director-${type}-templates_%s.json",
                date('YmdHis')
            );
            $this->getResponse()->setHeader('Content-disposition', "attachment; filename=$filename", true);
            $this->apiRequestHandler()->dispatch();
            return;
        }

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

        if ($this->params->get('render') === 'tree') {
            TemplateTreeRenderer::showType($shortType, $this, $this->db());
        } else {
            $table = TemplatesTable::create($shortType, $this->db());
            $this->eventuallyFilterCommand($table);
            $table->renderTo($this);
            (new AdditionalTableActions($this->getAuth(), $this->url(), $table))
                ->appendTo($this->actions());
        }
    }

    /**
     * @return $this
     * @throws \Icinga\Security\SecurityException
     */
    protected function assertApplyRulePermission()
    {
        return $this->assertPermission('director/admin');
    }

    /**
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws \Icinga\Security\SecurityException
     * @throws NotFoundError
     */
    public function applyrulesAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            $this->apiRequestHandler()->dispatch();
            return;
        }

        $type = $this->getType();

        if ($this->params->get('format') === 'json') {
            $filename = sprintf(
                "director-${type}-applyrules_%s.json",
                date('YmdHis')
            );
            $this->getResponse()->setHeader('Content-disposition', "attachment; filename=$filename", true);
            $this->apiRequestHandler()->dispatch();
            return;
        }

        $tType = $this->translate(ucfirst($type));
        $this
            ->assertApplyRulePermission()
            ->addObjectsTabs()
            ->setAutorefreshInterval(10)
            ->addTitle(
                $this->translate('All your %s Apply Rules'),
                $tType
            );
        $baseUrl = 'director/' . $this->getBaseObjectUrl();
        $this->actions()
            //->add($this->getBackToDashboardLink())
            ->add(
                Link::create(
                    $this->translate('Add'),
                    "${baseUrl}/add",
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

        $table = $this->getApplyRulesTable();
        $table->renderTo($this);
        (new AdditionalTableActions($this->getAuth(), $this->url(), $table))
            ->appendTo($this->actions());
    }

    /**
     * @throws NotFoundError
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws \Icinga\Security\SecurityException
     */
    public function setsAction()
    {
        $type = $this->getType();
        $tType = $this->translate(ucfirst($type));
        $this
            ->assertPermission('director/' . $this->getBaseType() . 'sets')
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

        ObjectSetTable::create($type, $this->db(), $this->getAuth())
            ->setBranch($this->getBranch())
            ->renderTo($this);
    }

    /**
     * @return array
     * @throws NotFoundError
     */
    protected function loadMultiObjectsFromParams()
    {
        $filter = Filter::fromQueryString($this->params->toString());
        $type = $this->getType();
        $objects = array();
        $db = $this->db();
        $class = DbObjectTypeRegistry::classByType($type);
        $table = DbObjectTypeRegistry::tableNameByType($type);
        $store = new DbObjectStore($db, $this->getBranch());

        /** @var $filter FilterChain */
        foreach ($filter->filters() as $sub) {
            /** @var $sub FilterChain */
            foreach ($sub->filters() as $ex) {
                /** @var $ex FilterChain|FilterExpression */
                $col = $ex->getColumn();
                if ($ex->isExpression()) {
                    if ($col === 'name') {
                        $name = $ex->getExpression();
                        if ($type === 'service') {
                            $key = [
                                'object_type' => 'template',
                                'object_name' => $name
                            ];
                        } else {
                            $key = $name;
                        }
                        $objects[$name] = $class::load($key, $db);
                    } elseif ($col === 'id') {
                        $name = $ex->getExpression();
                        $objects[$name] = $class::load($name, $db);
                    } elseif ($col === 'uuid') {
                        $object = $store->load($table, Uuid::fromString($ex->getExpression()));
                        $objects[$object->getObjectName()] = $object;
                    } else {
                        throw new InvalidArgumentException("'$col' is no a valid key component for '$type'");
                    }
                }
            }
        }

        return $objects;
    }

    /**
     * @param string $name
     *
     * @return \Icinga\Module\Director\Web\Form\QuickForm
     */
    public function loadForm($name)
    {
        $form = FormLoader::load($name, $this->Module());
        if ($this->getRequest()->isApiRequest()) {
            // TODO: Ask form for API support?
            $form->setApiRequest();
        }

        return $form;
    }

    /**
     * @param ZfQueryBasedTable $table
     * @return ZfQueryBasedTable
     * @throws NotFoundError
     */
    protected function eventuallyFilterCommand(ZfQueryBasedTable $table)
    {
        if ($this->params->get('command')) {
            $command = IcingaCommand::load($this->params->get('command'), $this->db());
            switch ($this->getBaseType()) {
                case 'host':
                case 'service':
                    $table->getQuery()->where(
                        $this->db()->getDbAdapter()->quoteInto(
                            '(o.check_command_id = ? OR o.event_command_id = ?)',
                            $command->getAutoincId()
                        )
                    );
                    break;
                case 'notification':
                    $table->getQuery()->where(
                        'o.command_id = ?',
                        $command->getAutoincId()
                    );
                    break;
            }
        }

        return $table;
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

    /**
     * @param $feature
     * @return bool
     */
    protected function supports($feature)
    {
        $func = "supports$feature";
        return IcingaObject::createByType($this->getType())->$func();
    }

    /**
     * @return string
     */
    protected function getBaseType()
    {
        $type = $this->getType();
        if (substr($type, -5) === 'Group') {
            return substr($type, 0, -5);
        } else {
            return $type;
        }
    }

    protected function getBaseObjectUrl()
    {
        return $this->getType();
    }

    /**
     * @return string
     */
    protected function getType()
    {
        // Strip final 's' and upcase an eventual 'group'
        return preg_replace(
            array('/group$/', '/period$/', '/argument$/', '/apiuser$/', '/dependencie$/', '/set$/'),
            array('Group', 'Period', 'Argument', 'ApiUser', 'dependency', 'Set'),
            str_replace(
                'template',
                '',
                substr($this->getRequest()->getControllerName(), 0, -1)
            )
        );
    }

    /**
     * @return string
     */
    protected function getPluralType()
    {
        return preg_replace('/cys$/', 'cies', $this->getType() . 's');
    }

    /**
     * @return string
     */
    protected function getPluralBaseType()
    {
        return preg_replace('/cys$/', 'cies', $this->getBaseType() . 's');
    }
}
