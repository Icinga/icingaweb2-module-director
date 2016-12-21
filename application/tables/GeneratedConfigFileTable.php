<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class GeneratedConfigFileTable extends QuickTable
{
    protected $deploymentId;

    protected $activeFile;

    public function getColumns()
    {
        $columns = array(
            'file_path'       => 'cf.file_path',
            'size'            => 'LENGTH(f.content)',
            'cnt_object'      => 'f.cnt_object',
            'cnt_template'    => 'f.cnt_template',
            'cnt_apply'       => 'f.cnt_apply',
            'cnt_all'         => "f.cnt_object || ' / ' || f.cnt_template || ' / ' || f.cnt_apply",
            'checksum'        => 'LOWER(HEX(f.checksum))',
            'config_checksum' => 'LOWER(HEX(cf.config_checksum))',
        );

        if ($this->connection->isPgsql()) {
            $columns['checksum'] = "LOWER(ENCODE(f.checksum, 'hex'))";
            $columns['config_checksum'] = "LOWER(ENCODE(cf.config_checksum, 'hex'))";
        }

        return $columns;
    }

    public function setActiveFilename($filename)
    {
        $this->activeFile = $filename;
        return $this;
    }

    protected function getRowClasses($row)
    {
        if ($row->file_path === $this->activeFile) {
            return 'active';
        }

        return parent::getRowClasses($row);
    }

    protected function getActionUrl($row)
    {
        $params = array(
            'config_checksum' => $row->config_checksum,
            'file_path'       => $row->file_path
        );

        if ($this->deploymentId) {
            $params['deployment_id'] = $this->deploymentId;
        }

        return $this->url('director/config/file', $params);
    }

    public function setDeploymentId($id)
    {
        $this->deploymentId = $id;
        return $this;
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'file_path'    => $view->translate('File'),
            'cnt_all'      => $view->translate('Object/Tpl/Apply'),
            /*
            'cnt_object'   => $view->translate('Objects'),
            'cnt_template' => $view->translate('Templates'),
            'cnt_apply'    => $view->translate('Apply rules'),
            */
            'size'         => $view->translate('Size'),
        );
    }

    public function setConfigChecksum($checksum)
    {
        $this->enforceFilter('config_checksum', $checksum);
        return $this;
    }

    public function getBaseQuery()
    {
        return $this->db()->select()->from(
            array('cf' => 'director_generated_config_file'),
            array()
        )->join(
            array('f' => 'director_generated_file'),
            'cf.file_checksum = f.checksum',
            array()
        )->order('cf.file_path ASC');
    }
}
