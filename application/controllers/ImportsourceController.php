<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\ImportRowModifierForm;
use Icinga\Module\Director\Forms\ImportSourceForm;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Web\Form\CloneImportSourceForm;
use Icinga\Module\Director\Web\Table\ImportrunTable;
use Icinga\Module\Director\Web\Table\ImportsourceHookTable;
use Icinga\Module\Director\Web\Table\PropertymodifierTable;
use Icinga\Module\Director\Web\Tabs\ImportsourceTabs;
use Icinga\Module\Director\Web\Widget\ImportSourceDetails;
use dipl\Html\Link;

class ImportsourceController extends ActionController
{
    /**
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     */
    public function init()
    {
        parent::init();

        $id = $this->params->get('source_id', $this->params->get('id'));
        $tabs = $this->tabs(new ImportsourceTabs($id));
        $action = $this->getRequest()->getActionName();
        if ($tabs->has($action)) {
            $tabs->activate($action);
        }
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function indexAction()
    {
        $source = ImportSource::load($this->params->getRequired('id'), $this->db());
        if ($this->params->get('format') === 'json') {
            $this->sendJson($this->getResponse());
            return;
        }
        $this->addTitle(
            $this->translate('Import source: %s'),
            $source->get('source_name')
        )->setAutorefreshInterval(10);
        $this->actions()->add(
            Link::create(
                $this->translate('Download JSON'),
                $this->url()->with('format', 'json'),
                null,
                [
                    'data-base-target' => '_blank',

                ]
            )
        );

        $this->content()->add(new ImportSourceDetails($source));
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     */
    public function addAction()
    {
        $this->addTitle($this->translate('Add import source'))
            ->content()->add(
                ImportSourceForm::load()->setDb($this->db())
                    ->setSuccessUrl('director/importsources')
                    ->handleRequest()
            );
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\MissingParameterException
     */
    public function editAction()
    {
        $id = $this->params->getRequired('id');
        $form = ImportSourceForm::load()->setDb($this->db())
            ->loadObject($id)
            ->setListUrl('director/importsources')
            ->handleRequest();
        $this->actions()->add(
            Link::create(
                $this->translate('Clone'),
                'director/importsource/clone',
                ['id' => $id],
                ['class' => 'icon-paste']
            )
        );
        $this->addTitle(
            $this->translate('Import source: %s'),
            $form->getObject()->get('source_name')
        )->setAutorefreshInterval(10);

        $this->content()->add($form);
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function cloneAction()
    {
        $id = $this->params->getRequired('id');
        $source = ImportSource::load($id, $this->db());
        $this->tabs()->add('show', [
            'url'       => 'director/importsource',
            'urlParams' => ['id' => $id],
            'label'     => $this->translate('Import Source'),
        ])->add('clone', [
            'url'       => 'director/importsource/clone',
            'urlParams' => ['id' => $id],
            'label'     => $this->translate('Clone'),
        ])->activate('clone');
        $this->addTitle('Clone: %s', $source->get('source_name'));
        $this->actions()->add(
            Link::create(
                $this->translate('Modify'),
                'director/importsource/edit',
                ['id' => $source->get('id')],
                ['class' => 'icon-paste']
            )
        );

        $form = new CloneImportSourceForm($source);
        $this->content()->add($form);
        $form->handleRequest($this->getRequest());
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function previewAction()
    {
        $source = ImportSource::load($this->params->getRequired('id'), $this->db());

        $this->addTitle(
            $this->translate('Import source preview: %s'),
            $source->get('source_name')
        );

        $this->actions()->add(Link::create('[..]', '#', null, [
            'onclick' => 'javascript:$("table.raw-data-table").toggleClass("collapsed");'
        ]));
        (new ImportsourceHookTable())->setImportSource($source)->renderTo($this);
    }

    /**
     * @return ImportSource
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function requireImportSourceAndAddModifierTable()
    {
        $source = ImportSource::load($this->params->getRequired('source_id'), $this->db());
        PropertymodifierTable::load($source, $this->url())
            ->handleSortPriorityActions($this->getRequest(), $this->getResponse())
            ->renderTo($this);

        return $source;
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function modifierAction()
    {
        $source = $this->requireImportSourceAndAddModifierTable();
        $this->addTitle($this->translate('Property modifiers: %s'), $source->get('source_name'));
        $this->addAddLink(
            $this->translate('Add property modifier'),
            'director/importsource/addmodifier',
            ['source_id' => $source->getId()],
            '_self'
        );
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function historyAction()
    {
        $source = ImportSource::load($this->params->getRequired('id'), $this->db());
        $this->addTitle($this->translate('Import run history: %s'), $source->get('source_name'));

        // TODO: temporarily disabled, find a better place for stats:
        // $this->view->stats = $this->db()->fetchImportStatistics();
        ImportrunTable::load($source)->renderTo($this);
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws \Icinga\Exception\MissingParameterException
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

        $this->content()->prepend(
            ImportRowModifierForm::load()->setDb($this->db())
                ->setSource($source)
                ->setSuccessUrl(
                    'director/importsource/modifier',
                    ['source_id' => $source->getId()]
                )->handleRequest()
        );
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function editmodifierAction()
    {
        // We need to load the table AFTER adding the title, otherwise search
        // will not be placed next to the title
        $source = ImportSource::load($this->params->getRequired('source_id'), $this->db());

        $this->addTitle(
            $this->translate('%s: Property Modifier'),
            $source->get('source_name')
        )->addBackToModifiersLink($source);
        $source = $this->requireImportSourceAndAddModifierTable();
        $this->tabs()->activate('modifier');

        $listUrl = 'director/importsource/modifier?source_id='
            . (int) $source->get('id');
        $this->content()->prepend(
            ImportRowModifierForm::load()->setDb($this->db())
                ->loadObject($this->params->getRequired('id'))
                ->setListUrl($listUrl)
                ->setSource($source)
                ->handleRequest()
        );
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
                ['source_id' => $source->getId()],
                ['class' => 'icon-left-big']
            )
        );

        return $this;
    }
}
