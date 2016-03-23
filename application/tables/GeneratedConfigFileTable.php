<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class GeneratedConfigFileTable extends QuickTable
{
    protected $deploymentId;

    public function getColumns()
    {
        $columns = array(
            'file_path'       => 'cf.file_path',
            'size'            => 'LENGTH(f.content)',
            'cnt_object'      => 'f.cnt_object',
            'cnt_template'    => 'f.cnt_template',
            'checksum'        => 'LOWER(HEX(f.checksum))',
            'config_checksum' => 'LOWER(HEX(cf.config_checksum))',
        );

        if ($this->connection->isPgsql()) {
            $columns['checksum'] = "LOWER(ENCODE(f.checksum, 'hex'))";
            $columns['config_checksum'] = "LOWER(ENCODE(cf.config_checksum, 'hex'))";
        }

        return $columns;
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
            'cnt_object'   => $view->translate('Objects'),
            'cnt_template' => $view->translate('Templates'),
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
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('cf' => 'director_generated_config_file'),
            array()
        )->join(
            array('f' => 'director_generated_file'),
            'cf.file_checksum = f.checksum',
            array()
        )->order('cf.file_path ASC');

        return $query;
    }
}
