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
        // Use direct SQL to avoid ORM hooks that may throw in production.
        $db = $this->db()->getDbAdapter();
        $id = (int) $db->fetchOne(
            $db->select()->from('import_source', 'id')->where('source_name = ?', $name)
        );
        if (!$id) {
            $this->sendJson($this->getResponse(), (object)[]);
            return;
        }

        // Delete child rows in dependency order.
        // 1. sync_property references this source
        $db->delete('sync_property', ['source_id = ?' => $id]);

        // 2. modifier settings before modifiers
        $modifierIds = $db->fetchCol(
            $db->select()->from('import_row_modifier', 'id')->where('source_id = ?', $id)
        );
        if (!empty($modifierIds)) {
            $db->delete('import_row_modifier_setting', ['row_modifier_id IN (?)' => $modifierIds]);
        }
        $db->delete('import_row_modifier', ['source_id = ?' => $id]);

        // 3. source settings and runs (imported_rowset rows are content-addressed
        //    and shared; do not delete them)
        $db->delete('import_source_setting', ['source_id = ?' => $id]);
        $db->delete('import_run', ['source_id = ?' => $id]);

        // 4. the source itself
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
