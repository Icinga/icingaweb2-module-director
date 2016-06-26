<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Import\Import;
use Icinga\Web\Notification;
use Icinga\Web\Url;

class ImportsourceController extends ActionController
{
    public function indexAction()
    {
        $id = $this->params->get('id');
        $this->prepareTabs($id)->activate('show');
        $source = $this->view->source = ImportSource::load($id, $this->db());
        $this->view->title = sprintf(
            $this->translate('Import source: %s'),
            $source->source_name
        );

        $this->view->checkForm = $this
            ->loadForm('ImportCheck')
            ->setImportSource($source)
            ->handleRequest();

        $this->view->runForm = $this
            ->loadForm('ImportRun')
            ->setImportSource($source)
            ->handleRequest();
    }

    public function addAction()
    {
        $this->editAction();
    }

    public function editAction()
    {
        $id = $this->params->get('id');

        $form = $this->view->form = $this->loadForm('importSource')->setDb($this->db());

        if ($id) {
            $form->loadObject($id)->setListUrl('director/list/importsource');
            $this->prepareTabs($id)->activate('edit');
            $this->view->title = $this->translate('Edit import source');
        } else {
            $form->setSuccessUrl('director/list/importsource');
            $this->view->title = $this->translate('Add import source');
            $this->prepareTabs()->activate('add');
        }

        $form->handleRequest();
        $this->setViewScript('object/form');
    }

    public function previewAction()
    {
        $id = $this->params->get('id');

        $source = ImportSource::load($id, $this->db());
        $this->prepareTabs($id)->activate('preview');

        $this->view->title = sprintf(
            $this->translate('Import source preview: "%s"'),
            $source->source_name
        );

        $this->view->table = $this->applyPaginationLimits(
            $this->loadTable('importsourceHook')
                ->setConnection($this->db())
                ->setImportSource($source)
        );
        $this->setViewScript('list/table');
    }

    public function modifierAction()
    {
        $this->view->stayHere = true;
        $id = $this->params->get('source_id');
        $this->prepareTabs($id)->activate('modifier');

        $this->view->addLink = $this->view->qlink(
            $this->translate('Add property modifier'),
            'director/importsource/addmodifier',
            array('source_id' => $id),
            array('class' => 'icon-plus')
        );

        $this->view->title = $this->translate('Property modifiers');
        $this->view->table = $this->loadTable('propertymodifier')
            ->enforceFilter(Filter::where('source_id', $id))
            ->setConnection($this->db());
        $this->setViewScript('list/table');
    }

    public function historyAction()
    {
        $url = $this->getRequest()->getUrl();
        $id = $url->shift('id');
        if ($url->shift('action') === 'remove') {
            $this->view->form = $this->loadForm('removeImportrun');
        }

        $this->prepareTabs($id)->activate('history');
        $this->view->title = $this->translate('Import run history');
        $this->view->stats = $this->db()->fetchImportStatistics();
        $this->prepareTable('importrun');
        $this->view->table->enforceFilter(Filter::where('source_id', $id));
    }

    public function editmodifierAction()
    {
        $this->addmodifierAction();
    }

    public function addmodifierAction()
    {
        $this->view->stayHere = true;
        $edit = false;

        if ($id = $this->params->get('id')) {
            $edit = true;
        }

        $form = $this->view->form = $this->loadForm('importRowModifier')->setDb($this->db());

        if ($edit) {
            $form->loadObject($id);
            $source_id = $form->getObject()->source_id;
            $form->setSource(ImportSource::load($source_id, $this->db()));
        } elseif ($source_id = $this->params->get('source_id')) {
            $form->setSource(ImportSource::load($source_id, $this->db()));
        }
        $form->setSuccessUrl('director/importsource/modifier', array('source_id' => $source_id));

        $form->handleRequest();

        $tabs = $this->prepareTabs($source_id)->activate('modifier');

        $this->view->title = $this->translate('Modifier'); // add/edit
        $this->view->table = $this->loadTable('propertymodifier')
            ->enforceFilter(Filter::where('source_id', $source_id))
            ->setConnection($this->db());

        $this->setViewScript('list/table');
    }

    protected function prepareTabs($id = null)
    {
        $tabs = $this->getTabs();

        if ($id) {
            $tabs->add('show', array(
                'url'       => 'director/importsource' . '?id=' . $id,
                'label'     => $this->translate('Import source'),
            ))->add('edit', array(
                'url'       => 'director/importsource/edit' . '?id=' . $id,
                'label'     => $this->translate('Modify'),
            ))->add('modifier', array(
                'url'       => 'director/importsource/modifier' . '?source_id=' . $id,
                'label'     => $this->translate('Modifiers'),
            ))->add('history', array(
                'url'       => 'director/importsource/history' . '?id=' . $id,
                'label'     => $this->translate('History'),
            ))->add('preview', array(
                'url'       => 'director/importsource/preview' . '?id=' . $id,
                'label'     => $this->translate('Preview'),
            ));

        } else {
            $tabs->add('add', array(
                'url'       => 'director/importsource/add',
                'label'     => $this->translate('New import source'),
            ))->activate('add');
        }

        return $tabs;
    }
}
