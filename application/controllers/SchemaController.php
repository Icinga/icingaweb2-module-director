<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;
use dipl\Html\Html;
use dipl\Html\Link;

class SchemaController extends ActionController
{
    protected $schemas;

    public function init()
    {
        $this->schemas = [
            'mysql' => $this->translate('MySQL schema'),
            'pgsql' => $this->translate('PostgreSQL schema'),
        ];
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     */
    public function mysqlAction()
    {
        $this->serveSchema('mysql');
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     */
    public function pgsqlAction()
    {
        $this->serveSchema('pgsql');
    }

    /**
     * @param $type
     * @throws \Icinga\Exception\IcingaException
     */
    protected function serveSchema($type)
    {
        $schema = $this->loadSchema($type);

        if ($this->params->get('format') === 'sql') {
            header('Content-type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $type . '.sql');
            echo $schema;
            exit;
            // TODO: Shutdown
        }

        $this
            ->addSchemaTabs($type)
            ->addTitle($this->schemas[$type])
            ->addDownloadAction()
            ->content()->add(Html::tag('pre', null, $schema));
    }

    protected function loadSchema($type)
    {
        return file_get_contents(
            sprintf(
                '%s/schema/%s.sql',
                $this->Module()->getBasedir(),
                $type
            )
        );
    }

    /**
     * @return $this
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
    protected function addDownloadAction()
    {
        $this->actions()->add(
            Link::create(
                $this->translate('Download'),
                $this->url()->with('format', 'sql'),
                null,
                [
                    'target' => '_blank',
                    'class'  => 'icon-download',
                ]
            )
        );

        return $this;
    }

    /**
     * @param $active
     * @return $this
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws \Icinga\Exception\ProgrammingError
     */
    protected function addSchemaTabs($active)
    {
        $tabs = $this->tabs();
        foreach ($this->schemas as $type => $title) {
            $tabs->add($type, [
                'url'   => 'director/schema/' . $type,
                'label' => $title,
            ]);
        }

        $tabs->activate($active);

        return $this;
    }
}
