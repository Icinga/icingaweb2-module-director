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
    public function addAction()
    {
        $this->indexAction();
    }

    public function editAction()
    {
        $this->indexAction();
    }

    public function runAction()
    {
        $id = $this->params->get('id');
        $import = new Import(ImportSource::load($id, $this->db()));
        if ($runId = $import->run()) {
            Notification::success('Import succeeded');
            $this->redirectNow(Url::fromPath('director/importrun', array('id' => $runId)));
        } else {
            Notification::success('Import skipped, no changes detected');
            $this->redirectNow('director/list/importrun');
        }
    }

    public function previewAction()
    {
        $id = $this->params->get('id');

        $this->view->addLink = $this->view->qlink(
            $this->translate('Run'),
            'director/importsource/run',
            array('id' => $id)
        );

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
        $this->render('list/table', null, true);
    }

    public function modifierAction()
    {
        $this->view->stayHere = true;
        $id = $this->params->get('source_id');
        $this->prepareTabs($id)->activate('modifier');

        $this->view->addLink = $this->view->icon('plus')
            . ' '
            . $this->view->qlink(
                $this->translate('Add property modifier'),
                'director/importsource/addmodifier',
                array('source_id' => $id)
            );

        $this->view->title = $this->translate('Property modifiers');
        $this->view->table = $this->loadTable('propertymodifier')
            ->enforceFilter(Filter::where('source_id', $id))
            ->setConnection($this->db());
        $this->render('list/table', null, true);
    }

    public function indexAction()
    {
        $id = $this->params->get('id');

        $form = $this->view->form = $this->loadForm('importSource')
            ->setSuccessUrl('director/list/importsource')
            ->setDb($this->db());

        if ($id) {
            $form->loadObject($id);
            $this->prepareTabs($id)->activate('edit');
            $this->view->title = $this->translate('Edit import source');
        } else {
            $this->view->title = $this->translate('Add import source');
            $this->prepareTabs()->activate('add');
        }

        $form->handleRequest();
        $this->render('object/form', null, true);
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

        $this->render('list/table', null, true);
    }

    protected function prepareTabs($id = null)
    {
        $tabs = $this->getTabs();

        if ($id) {
            $tabs->add('edit', array(
                'url'       => 'director/importsource/edit' . '?id=' . $id,
                'label'     => $this->translate('Import source'),
            ))->add('modifier', array(
                'url'       => 'director/importsource/modifier' . '?source_id=' . $id,
                'label'     => $this->translate('Modifiers'),
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
