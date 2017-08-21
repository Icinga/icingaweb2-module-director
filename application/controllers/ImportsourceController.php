<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\ImportRowModifierForm;
use Icinga\Module\Director\Forms\ImportSourceForm;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Web\Table\ImportrunTable;
use Icinga\Module\Director\Web\Table\ImportsourceHookTable;
use Icinga\Module\Director\Web\Table\PropertymodifierTable;
use Icinga\Module\Director\Web\Tabs\ImportsourceTabs;
use Icinga\Module\Director\Web\Widget\ImportSourceDetails;
use ipl\Html\Link;

class ImportsourceController extends ActionController
{
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

    public function indexAction()
    {
        $source = ImportSource::load($this->params->getRequired('id'), $this->db());
        $this->addTitle(
            $this->translate('Import source: %s'),
            $source->get('source_name')
        )->setAutorefreshInterval(10);

        $this->content()->add(new ImportSourceDetails($source));
    }

    public function addAction()
    {
        $this->addTitle($this->translate('Add import source'))
            ->content()->add(
                ImportSourceForm::load()->setDb($this->db())
                    ->setSuccessUrl('director/importsources')
                    ->handleRequest()
            );
    }

    public function editAction()
    {
        $form = ImportSourceForm::load()->setDb($this->db())
            ->loadObject($this->params->getRequired('id'))
            ->setListUrl('director/importsources')
            ->handleRequest();

        $this->content()->add($form);
    }

    public function previewAction()
    {
        $source = ImportSource::load($this->params->getRequired('id'), $this->db());

        $this->addTitle(
            $this->translate('Import source preview: "%s"'),
            $source->get('source_name')
        );

        (new ImportsourceHookTable())->setImportSource($source)->renderTo($this);
    }

    protected function requireImportSourceAndAddModifierTable()
    {
        $source = ImportSource::load($this->params->getRequired('source_id'), $this->db());
        PropertymodifierTable::load($source, $this->url())
            ->handleSortPriorityActions($this->getRequest(), $this->getResponse())
            ->renderTo($this);
        return $source;
    }

    public function modifierAction()
    {
        $this->addTitle($this->translate('Property modifiers'));
        $source = $this->requireImportSourceAndAddModifierTable();
        $this->addAddLink(
            $this->translate('Add property modifier'),
            'director/importsource/addmodifier',
            ['source_id' => $source->getId()],
            '_self'
        );
    }

    public function historyAction()
    {
        $source = ImportSource::load($this->params->getRequired('id'), $this->db());
        $this->addTitle($this->translate('Import run history'));

        // TODO: temporarily disabled, find a better place for stats:
        // $this->view->stats = $this->db()->fetchImportStatistics();
        ImportrunTable::load($source)->renderTo($this);
    }

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

    public function editmodifierAction()
    {
        $source = $this->requireImportSourceAndAddModifierTable();
        $this->addTitle(
            $this->translate('%s: Property Modifier'),
            $source->get('source_name')
        )->addBackToModifiersLink($source);
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
