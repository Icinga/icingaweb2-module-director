<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\DirectorObject\Automation\ImportExport;
use Icinga\Module\Director\Web\Table\ImportsourceTable;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Tabs\ImportTabs;

class ImportsourcesController extends ActionController
{
    protected $isApified = true;

    public function indexAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            switch (strtolower($this->getRequest()->getMethod())) {
                case 'get':
                    $this->sendExport();
                    break;
                case 'post':
                    $this->acceptImport($this->getRequest()->getRawBody());
                    break;
                case 'delete':
                    $this->deleteImportSource($this->params->get('name'));
                    break;
                default:
                    $this->sendUnsupportedMethod();
            }

            return;
        }

        $this->addTitle($this->translate('Import source'))
            ->setAutorefreshInterval(10)
            ->addAddLink(
                $this->translate('Add a new Import Source'),
                'director/importsource/add'
            )->tabs(new ImportTabs())->activate('importsource');

        (new ImportsourceTable($this->db()))->renderTo($this);
    }

    protected function deleteImportSource($name)
    {
        $db = $this->db()->getDbAdapter();
        $id = (int) $db->fetchOne(
            $db->select()->from('import_source', 'id')->where('source_name = ?', $name)
        );
        if (!$id) {
            $this->sendJson($this->getResponse(), (object)[]);
            return;
        }

        $db->delete('sync_property', ['source_id = ?' => $id]);

        $modifierIds = $db->fetchCol(
            $db->select()->from('import_row_modifier', 'id')->where('source_id = ?', $id)
        );
        if (!empty($modifierIds)) {
            $db->delete('import_row_modifier_setting', ['row_modifier_id IN (?)' => $modifierIds]);
        }
        $db->delete('import_row_modifier', ['source_id = ?' => $id]);
        $db->delete('import_source_setting', ['source_id = ?' => $id]);
        $db->delete('import_run', ['source_id = ?' => $id]);
        $db->delete('import_source', ['id = ?' => $id]);

        $this->sendJson($this->getResponse(), (object)[]);
    }

    /**
     * @param $raw
     */
    protected function acceptImport($raw)
    {
        (new ImportExport($this->db()))->unserializeImportSources(json_decode($raw));
        $this->sendJson($this->getResponse(), (object) ['status' => 'OK']);
    }

    protected function sendExport()
    {
        $this->sendJson(
            $this->getResponse(),
            (new ImportExport($this->db()))->serializeAllImportSources()
        );
    }
}
