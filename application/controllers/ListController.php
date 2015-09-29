<?php

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Core\RestApiClient;
use Icinga\Module\Director\Core\CoreApi;
use Exception;

class Director_ListController extends ActionController
{
    public function activitylogAction()
    {
        $this->setConfigTabs()->activate('activitylog');
        $this->view->title = $this->translate('Activity Log');
        $this->prepareAndRenderTable('activityLog');
    }

    public function datalistAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add list'),
            'director/datalist/add'
        );

        $this->setConfigTabs()->activate('datalist');
        $this->view->title = $this->translate('Data lists');
        $this->prepareAndRenderTable('datalist');
    }

    public function importsourceAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add import source'),
            'director/importsource/add'
        );

        $this->setImportTabs()->activate('importsource');
        $this->view->title = $this->translate('Import source');
        $this->prepareAndRenderTable('importsource');
    }

    public function importrunAction()
    {
        $this->setImportTabs()->activate('importrun');
        $this->view->title = $this->translate('Import runs');
        $this->view->stats = $this->db()->fetchImportStatistics();
        $this->prepareAndRenderTable('importrun');
    }

    public function syncruleAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add sync rule'),
            'director/syncrule/add'
        );

        $this->setImportTabs()->activate('syncrule');
        $this->view->title = $this->translate('Sync rule');
        $this->view->table = $this->loadTable('syncrule')->setConnection($this->db());
        $this->render('table');
    }

    public function syncpropertyAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add sync property rule'),
            'director/syncproperty/add'
        );

        $this->setImportTabs()->activate('syncproperty');
        $this->view->title = $this->translate('Sync property');
        $this->view->table = $this->loadTable('syncproperty')->setConnection($this->db());
        $this->render('table');
    }

    public function datalistentryAction()
    {
        $listId = $this->params->get('list_id');
        $this->view->lastId = $listId;

        $this->view->addLink = $this->view->qlink(
            $this->translate('Add entry'),
            'director/datalistentry/add' . '?list_id=' . $listId
        );

        $this->view->title = $this->translate('List entries');
        $this->getTabs()->add('editlist', array(
            'url'       => 'director/datalist/edit' . '?id=' . $listId,
            'label'     => $this->translate('Edit list'),
        ))->add('datalistentry', array(
            'url'       => 'director/datalistentry' . '?list_id=' . $listId,
            'label'     => $this->view->title,
        ))->activate('datalistentry');

        $this->prepareAndRenderTable('datalistEntry');
    }

    public function datafieldAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add field'),
            'director/datafield/add'
        );

        $this->setConfigTabs()->activate('datafield');
        $this->view->title = $this->translate('Data fields');
        $this->prepareAndRenderTable('datafield');
    }

    public function generatedconfigAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Generate'),
            'director/config/store'
        );

        $this->setConfigTabs()->activate('generatedconfig');
        $this->view->title = $this->translate('Generated Configs');
        $this->prepareAndRenderTable('generatedConfig');
    }

    public function deploymentlogAction()
    {
	    $this->setAutorefreshInterval(5);
        try {
            $this->fetchLogs();
        } catch (Exception $e) {
            // No problem, Icinga might be reloading
        }

        $this->view->NOaddLink = $this->view->qlink(
            $this->translate('Deploy'),
            'director/config/deploy'
        );

        $this->setConfigTabs()->activate('deploymentlog');
        $this->view->title = $this->translate('Deployments');
        $this->prepareAndRenderTable('deploymentLog');
    }

    protected function fetchLogs()
    {
        $api = $this->api();
        foreach ($this->db()->getUncollectedDeployments() as $deployment) {
            $stage = $deployment->stage_name;
            try {
                $availableFiles = $api->listStageFiles($stage);
            } catch (Exception $e) {
                // This is not correct. We might miss logs as af an ongoing reload
                $deployment->stage_collected = 'y';
                $deployment->store();
                continue;
            }

            if (in_array('startup.log', $availableFiles)
                && in_array('status', $availableFiles))
            {
                if ($api->getStagedFile($stage, 'status') === '0') {
                    $deployment->startup_succeeded = 'y';
                } else {
                    $deployment->startup_succeeded = 'n';
                }
                $deployment->startup_log = $this->api()->getStagedFile($stage, 'startup.log');
            }

            $deployment->store();
        }

        // Not correct, we might clear logs we formerly skipped
        $api->wipeInactiveStages();
    }

    protected function api()
    {
        $apiconfig = $this->Config()->getSection('api');
        $client = new RestApiClient($apiconfig->get('address'), $apiconfig->get('port'));
        $client->setCredentials($apiconfig->get('username'), $apiconfig->get('password'));
        $api = new CoreApi($client);
        return $api;
    }

    protected function prepareAndRenderTable($name)
    {
        $table = $this->loadTable($name)->setConnection($this->db());
        $this->view->filterEditor = $table->getFilterEditor($this->getRequest());
        $this->view->table = $this->applyPaginationLimits($table);
        $this->render('table');
    }
}
