<?php

namespace Icinga\Module\Director\Controllers;

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
        if ($runId = Import::run($id = ImportSource::load($this->params->get('id'), $this->db()))) {
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
            'director/importsource/add',
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

    protected function prepareTabs($id = null)
    {
        $tabs = $this->getTabs();

        if ($id) {
            $tabs->add('edit', array(
                'url'       => 'director/importsource/edit' . '?id=' . $id,
                'label'     => $this->translate('Import source'),
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
