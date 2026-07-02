<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\DirectorObject\Automation\ImportExport;
use Icinga\Module\Director\Web\Table\SyncruleTable;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Tabs\ImportTabs;

class SyncrulesController extends ActionController
{
    protected $isApified = true;

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     */
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
                    $this->deleteSyncRule($this->params->get('name'));
                    break;
                default:
                    $this->sendUnsupportedMethod();
            }

            return;
        }

        $this->addTitle($this->translate('Sync rule'))
            ->setAutorefreshInterval(10)
            ->addAddLink(
                $this->translate('Add a new Sync Rule'),
                'director/syncrule/add'
            )->tabs(new ImportTabs())->activate('syncrule');

        (new SyncruleTable($this->db()))->renderTo($this);
    }

    protected function deleteSyncRule($name)
    {
        $db = $this->db()->getDbAdapter();
        $id = (int) $db->fetchOne(
            $db->select()->from('sync_rule', 'id')->where('rule_name = ?', $name)
        );
        if (!$id) {
            $this->sendJson($this->getResponse(), (object)[]);
            return;
        }
        $db->delete('sync_run', ['rule_id = ?' => $id]);
        $db->delete('sync_property', ['rule_id = ?' => $id]);
        $db->delete('sync_rule', ['id = ?' => $id]);
        $this->sendJson($this->getResponse(), (object)[]);
    }

    protected function acceptImport($raw)
    {
        $count = (new ImportExport($this->db()))->unserializeSyncRules(json_decode($raw));
        $this->sendJson($this->getResponse(), ['imported' => $count]);
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     */
    protected function sendExport()
    {
        $this->sendJson(
            $this->getResponse(),
            (new ImportExport($this->db()))->serializeAllSyncRules()
        );
    }
}
