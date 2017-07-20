<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;

class SchemaController extends ActionController
{
    protected $schema;

    public function init()
    {
        $this->schemas = array(
            'mysql' => $this->translate('MySQL schema'),
            'pgsql' => $this->translate('PostgreSQL schema'),
        );
    }

    protected function myTabs()
    {
        $tabs = $this->getTabs();
        foreach ($this->schemas as $type => $title) {
            $tabs->add($type, array(
                'url'   => 'director/schema/' . $type,
                'label' => $title,
            ));
        }
        return $tabs;
    }

    public function mysqlAction()
    {
        $this->serveSchema('mysql');
    }

    public function pgsqlAction()
    {
        $this->serveSchema('pgsql');
    }

    protected function serveSchema($type)
    {
        $schema = file_get_contents(
            sprintf(
                '%s/schema/%s.sql',
                $this->Module()->getBasedir(),
                $type
            )
        );

        if ($this->params->get('format') === 'sql') {
            header('Content-type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $type . '.sql');
            echo $schema;
            exit;
            // TODO: Shutdown
        } else {
            $this->myTabs()->activate($type);
            $this->view->title = $this->schemas[$type];
            $this->view->schema = $schema;
            $this->render('schema');
        }
    }
}
