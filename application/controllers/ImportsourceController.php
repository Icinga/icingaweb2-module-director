<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Director\Data\Exporter;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Forms\ImportRowModifierForm;
use Icinga\Module\Director\Forms\ImportSourceForm;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\ActionBar\AutomationObjectActionBar;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Web\Controller\BranchHelper;
use Icinga\Module\Director\Web\Form\CloneImportSourceForm;
use Icinga\Module\Director\Web\Table\ImportrunTable;
use Icinga\Module\Director\Web\Table\ImportsourceHookTable;
use Icinga\Module\Director\Web\Table\PropertymodifierTable;
use Icinga\Module\Director\Web\Tabs\ImportsourceTabs;
use Icinga\Module\Director\Web\Widget\ImportSourceDetails;
use InvalidArgumentException;
use gipfl\IcingaWeb2\Link;
use ipl\Html\Error;
use ipl\Html\Html;

class ImportsourceController extends ActionController
{
    use BranchHelper;

    /** @var ImportSource|null */
    private $importSource;

    private $id;

    /**
     * @throws \Icinga\Exception\AuthenticationException
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Security\SecurityException
     */
    public function init()
    {
        parent::init();
        $id = $this->params->get('source_id', $this->params->get('id'));
        if ($id !== null && is_numeric($id)) {
            $this->id = (int) $id;
        }

        $tabs = $this->tabs(new ImportsourceTabs($this->id));
        $action = $this->getRequest()->getActionName();
        if ($tabs->has($action)) {
            $tabs->activate($action);
        }
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function addMainActions()
    {
        $this->actions(new AutomationObjectActionBar(
            $this->getRequest()
        ));
        $source = $this->getImportSource();

        $this->actions()->add(Link::create(
            $this->translate('Add to Basket'),
            'director/basket/add',
            [
                'type'  => 'ImportSource',
                'names' => $source->getUniqueIdentifier()
            ],
            [
                'class' => 'icon-tag',
                'data-base-target' => '_next'
            ]
        ));
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function indexAction()
    {
        $this->addMainActions();
        $source = $this->getImportSource();
        if ($this->params->get('format') === 'json') {
            $this->sendJson($this->getResponse(), (new Exporter($this->db()))->export($source));
            return;
        }
        $this->addTitle(
            $this->translate('Import source: %s'),
            $source->get('source_name')
        )->setAutorefreshInterval(10);
        $branch = $this->getBranch();
        if ($this->getBranch()->isBranch()) {
            $this->content()->add(Hint::info(Html::sprintf($this->translate(
                'Please note that importing data will take place in your main Branch.'
                . ' Modifications to Import Sources are not allowed while being in a Configuration Branch.'
                . ' To get the full functionality, please deactivate %s'
            ), Branch::requireHook()->linkToBranch($branch, $this->getAuth(), $branch->getName()))));
        }
        $this->content()->add(new ImportSourceDetails($source));
    }

    public function addAction()
    {
        $this->addTitle($this->translate('Add import source'));
        if ($this->showNotInBranch($this->translate('Creating Import Sources'))) {
            return;
        }

        $this->content()->add(
            ImportSourceForm::load()->setDb($this->db())
                ->setSuccessUrl('director/importsources')
                ->handleRequest()
        );
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function editAction()
    {
        $this->addMainActions();
        $this->activateTabWithPostfix($this->translate('Modify'));
        if ($this->showNotInBranch($this->translate('Modifying Import Sources'))) {
            return;
        }
        $form = ImportSourceForm::load()
            ->setObject($this->getImportSource())
            ->setListUrl('director/importsources')
            ->handleRequest();
        $this->addTitle(
            $this->translate('Import source: %s'),
            $form->getObject()->get('source_name')
        )->setAutorefreshInterval(10);

        $this->content()->add($form);
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function cloneAction()
    {
        $this->addMainActions();
        $this->activateTabWithPostfix($this->translate('Clone'));
        if ($this->showNotInBranch($this->translate('Cloning Import Sources'))) {
            return;
        }
        $source = $this->getImportSource();
        $this->addTitle('Clone: %s', $source->get('source_name'));
        $form = new CloneImportSourceForm($source);
        $this->content()->add($form);
        $form->on(CloneImportSourceForm::ON_SUCCESS, function (CloneImportSourceForm $form) {
            $this->getResponse()->redirectAndExit($form->getSuccessUrl());
        });
        $form->handleRequest($this->getServerRequest());
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function previewAction()
    {
        $source = $this->getImportSource();

        $this->addTitle(
            $this->translate('Import source preview: %s'),
            $source->get('source_name')
        );
        $fetchUrl = clone($this->url());

        $this->actions()->add(Link::create(
            $this->translate('Download JSON'),
            $fetchUrl->setPath('director/importsource/fetch'),
            null,
            [
                'target' => '_blank',
                'class'  => 'icon-download',
            ]
        ))->add(Link::create('[..]', '#', null, [
            'onclick' => 'javascript:$("table.raw-data-table").toggleClass("collapsed");'
        ]));
        try {
            (new ImportsourceHookTable())->setImportSource($source)->renderTo($this);
        } catch (Exception $e) {
            $this->content()->add(Error::show($e));
        }
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function fetchAction()
    {
        $response = $this->getResponse();
        try {
            $source = $this->getImportSource();
            $source->checkForChanges();
            $hook = ImportSourceHook::forImportSource($source);
            $data = $hook->fetchData();
            $source->applyModifiers($data);

            $filename = sprintf(
                "director-importsource-%d_%s.json",
                $this->getParam('id'),
                date('YmdHis')
            );
            $response->setHeader('Content-Type', 'application/json', true);
            $response->setHeader('Content-disposition', "attachment; filename=$filename", true);
            $response->sendHeaders();
            $this->sendJson($this->getResponse(), $data);
        } catch (Exception $e) {
            $this->sendJsonError($response, $e->getMessage());
        }
        // TODO: this is not clean
        if (\ob_get_level()) {
            \ob_end_flush();
        }
        exit;
    }

    /**
     * @return ImportSource
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function requireImportSourceAndAddModifierTable()
    {
        $source = $this->getImportSource();
        $table = PropertymodifierTable::load($source, $this->url());
        if ($this->getBranch()->isBranch()) {
            $table->setReadOnly();
        } else {
            $table->handleSortPriorityActions($this->getRequest(), $this->getResponse());
        }
        $table->renderTo($this);

        return $source;
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function modifierAction()
    {
        $source = $this->requireImportSourceAndAddModifierTable();
        $this->addTitle($this->translate('Property modifiers: %s'), $source->get('source_name'));
        $this->addAddLink(
            $this->translate('Add property modifier'),
            'director/importsource/addmodifier',
            ['source_id' => $source->get('id')],
            '_self'
        );
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function historyAction()
    {
        $source = $this->getImportSource();
        $this->addTitle($this->translate('Import run history: %s'), $source->get('source_name'));

        // TODO: temporarily disabled, find a better place for stats:
        // $this->view->stats = $this->db()->fetchImportStatistics();
        ImportrunTable::load($source)->renderTo($this);
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function addmodifierAction()
    {
        $source = $this->requireImportSourceAndAddModifierTable();
        $this->addTitle(
            $this->translate('%s: add Property Modifier'),
            $source->get('source_name')
        )->addBackToModifiersLink($source);
        $this->tabs()->activate('modifier');

        if ($this->showNotInBranch($this->translate('Modifying Import Sources'))) {
            return;
        }

        $this->content()->prepend(
            ImportRowModifierForm::load()->setDb($this->db())
                ->setSource($source)
                ->setSuccessUrl(
                    'director/importsource/modifier',
                    ['source_id' => $source->get('id')]
                )->handleRequest()
        );
    }

    /**
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function editmodifierAction()
    {
        // We need to load the table AFTER adding the title, otherwise search
        // will not be placed next to the title
        $source = $this->getImportSource();

        $this->addTitle(
            $this->translate('%s: Property Modifier'),
            $source->get('source_name')
        )->addBackToModifiersLink($source);
        $source = $this->requireImportSourceAndAddModifierTable();
        $this->tabs()->activate('modifier');
        if ($this->showNotInBranch($this->translate('Modifying Import Sources'))) {
            return;
        }

        $listUrl = 'director/importsource/modifier?source_id='
            . (int) $source->get('id');
        $this->content()->prepend(
            ImportRowModifierForm::load()->setDb($this->db())
                ->loadObject((int) $this->params->getRequired('id'))
                ->setListUrl($listUrl)
                ->setSource($source)
                ->handleRequest()
        );
    }

    /**
     * @return ImportSource
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getImportSource()
    {
        if ($this->importSource === null) {
            if ($this->id === null) {
                throw new InvalidArgumentException('Got no ImportSource id');
            }
            $this->importSource = ImportSource::loadWithAutoIncId(
                $this->id,
                $this->db()
            );
        }

        return $this->importSource;
    }

    protected function activateTabWithPostfix($title)
    {
        /** @var ImportsourceTabs $tabs */
        $tabs = $this->tabs();
        $tabs->activateMainWithPostfix($title);

        return $this;
    }

    /**
     * @param ImportSource $source
     * @return $this
     */
    protected function addBackToModifiersLink(ImportSource $source)
    {
        $this->actions()->add(
            Link::create(
                $this->translate('back'),
                'director/importsource/modifier',
                ['source_id' => $source->get('id')],
                ['class' => 'icon-left-big']
            )
        );

        return $this;
    }
}
